<?php

namespace App\Services\FtpSubmission;

use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\FtpSubmissionAttachment;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
use App\Services\Documents\DeclarationAttachmentGatherer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Handles CAPS FTP attachment uploads (Spec 4.0 §1.9) and reads the
 * ETD<filename>_ATT_REP.TXT response files from the CAPS server.
 *
 * Naming convention:
 *   <T12 base filename>.<A-Z>.<extension>
 *   e.g.  10018405052026.001.A.pdf
 *
 * Response filename:
 *   ETD<T12 base filename>_ATT_REP.TXT
 *   e.g.  ETD10018405052026.001_ATT_REP.TXT
 *
 * Up to 26 attachments per ETD (letters A through Z).
 */
class CapsAttachmentUploader
{
    use FtpConnection;

    /**
     * Allowed attachment extensions per Spec 4.0 §1.9.
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'gif', 'bmp'];

    public const MAX_ATTACHMENTS_PER_SUBMISSION = 26;

    protected DeclarationAttachmentGatherer $gatherer;

    public function __construct(DeclarationAttachmentGatherer $gatherer)
    {
        $this->gatherer = $gatherer;
    }

    /**
     * Upload all gathered attachments for a submission. Idempotent — files
     * already in `uploaded` or `confirmed` state are skipped.
     *
     * @return array{uploaded: int, skipped: int, failed: int, attachments: array<int, FtpSubmissionAttachment>}
     */
    public function uploadForSubmission(
        WebFormSubmission $submission,
        DeclarationForm $declaration,
        Country $country,
        OrganizationSubmissionCredential $credentials
    ): array {
        $t12Filename = $submission->external_reference
            ?? $submission->request_data['filename']
            ?? null;

        if (empty($t12Filename)) {
            throw new \RuntimeException('Cannot upload attachments: T12 filename is unknown for submission ' . $submission->id);
        }

        $ftpSettings = $country->getFtpSettings();
        $ftpCreds = $credentials->getFtpCredentials();
        $traderId = $ftpCreds['trader_id'] ?? '';

        $rows = $this->prepareAttachmentRows($submission, $declaration, $t12Filename);

        if (empty($rows)) {
            Log::info('CAPS attachments: nothing to upload', [
                'submission_id' => $submission->id,
                'declaration_id' => $declaration->id,
            ]);
            return ['uploaded' => 0, 'skipped' => 0, 'failed' => 0, 'attachments' => []];
        }

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $persisted = [];

        try {
            $this->connect($ftpSettings, $ftpCreds);

            foreach ($rows as $row) {
                /** @var FtpSubmissionAttachment $attachment */
                $attachment = $row['attachment'];
                $persisted[] = $attachment;

                if (in_array($attachment->status, [
                    FtpSubmissionAttachment::STATUS_UPLOADED,
                    FtpSubmissionAttachment::STATUS_CONFIRMED,
                ], true)) {
                    $skipped++;
                    continue;
                }

                if (empty($row['localPath']) || !is_file($row['localPath'])) {
                    $attachment->update([
                        'status' => FtpSubmissionAttachment::STATUS_FAILED,
                        'error_message' => $row['missingReason'] ?? 'Local file not found',
                    ]);
                    $failed++;
                    continue;
                }

                $remotePath = $this->buildRemotePath($ftpSettings, $traderId, $attachment->remote_filename);

                try {
                    $this->uploadLocalFile($row['localPath'], $remotePath, FTP_BINARY);

                    $attachment->update([
                        'status' => FtpSubmissionAttachment::STATUS_UPLOADED,
                        'uploaded_at' => now(),
                        'error_message' => null,
                    ]);
                    $uploaded++;
                } catch (Throwable $e) {
                    $attachment->update([
                        'status' => FtpSubmissionAttachment::STATUS_FAILED,
                        'error_message' => $e->getMessage(),
                    ]);
                    $failed++;
                    Log::warning('CAPS attachment upload failed', [
                        'submission_id' => $submission->id,
                        'attachment_id' => $attachment->id,
                        'remote_path' => $remotePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $this->disconnect();
        }

        Log::info('CAPS attachment upload run complete', [
            'submission_id' => $submission->id,
            'uploaded' => $uploaded,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return [
            'uploaded' => $uploaded,
            'skipped' => $skipped,
            'failed' => $failed,
            'attachments' => $persisted,
        ];
    }

    /**
     * Poll the CAPS response folder for ETD<filename>_ATT_REP.TXT files for
     * every submission that has uploaded attachments awaiting confirmation.
     *
     * @return array{checked: int, updated: int}
     */
    public function pollResponses(?int $countryId = null): array
    {
        $query = WebFormSubmission::query()
            ->where('submission_type', WebFormSubmission::TYPE_FTP)
            ->whereHas('ftpAttachments', function ($q) {
                $q->where('status', FtpSubmissionAttachment::STATUS_UPLOADED);
            })
            ->with(['declaration.country', 'ftpAttachments']);

        if ($countryId) {
            $query->whereHas('declaration', function ($q) use ($countryId) {
                $q->where('country_id', $countryId);
            });
        }

        $submissions = $query->get();
        $checked = 0;
        $updated = 0;

        foreach ($submissions as $submission) {
            $checked++;
            try {
                if ($this->checkSubmissionStatus($submission)) {
                    $updated++;
                }
            } catch (Throwable $e) {
                Log::warning('CAPS attachment status poll failed', [
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['checked' => $checked, 'updated' => $updated];
    }

    /**
     * Check a single submission's attachment status by reading the response
     * file from the CAPS FTP server.
     *
     * @return bool true if any attachment row was updated
     */
    public function checkSubmissionStatus(WebFormSubmission $submission): bool
    {
        $declaration = $submission->declaration;
        if (!$declaration) {
            return false;
        }

        $country = $declaration->country;
        if (!$country || !$country->isFtpEnabled()) {
            return false;
        }

        $credentials = $declaration->organization?->submissionCredentials()
            ->whereIn('credential_type', ['ftp', 'caps'])
            ->where('country_id', $country->id)
            ->first();

        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            Log::info('CAPS status poll skipped: no credentials', [
                'submission_id' => $submission->id,
            ]);
            return false;
        }

        $t12Filename = $submission->external_reference
            ?? $submission->request_data['filename']
            ?? null;

        if (empty($t12Filename)) {
            return false;
        }

        $ftpSettings = $country->getFtpSettings();
        $ftpCreds = $credentials->getFtpCredentials();

        $responsePath = rtrim($ftpSettings['response_path'] ?? $ftpSettings['base_path'] ?? '/', '/');
        $responseFilename = 'ETD' . $t12Filename . '_ATT_REP.TXT';
        $remotePath = $responsePath . '/' . $responseFilename;

        $changed = false;

        try {
            $this->connect($ftpSettings, $ftpCreds);
            $content = $this->downloadToString($remotePath, FTP_ASCII);

            if ($content === null) {
                return false;
            }

            $changed = $this->applyResponseFile($submission, $content);

            if ($changed) {
                $this->archiveResponseLocally($submission, $responseFilename, $content);
            }
        } finally {
            $this->disconnect();
        }

        return $changed;
    }

    /**
     * Build remote filename per Spec 4.0 §1.9: <T12 base>.<A-Z>.<ext>
     */
    public function buildRemoteFilename(string $t12Filename, string $letter, string $extension): string
    {
        $letter = strtoupper(trim($letter));
        $extension = strtolower(ltrim(trim($extension), '.'));

        return $t12Filename . '.' . $letter . '.' . $extension;
    }

    /**
     * Reconcile DB attachment rows with the current declaration attachments.
     * Returns one row per attachment in upload order (existing + newly created).
     *
     * @return array<int, array{attachment: FtpSubmissionAttachment, localPath: ?string, missingReason: ?string}>
     */
    protected function prepareAttachmentRows(
        WebFormSubmission $submission,
        DeclarationForm $declaration,
        string $t12Filename
    ): array {
        $existing = $submission->ftpAttachments()->orderBy('letter')->get()
            ->keyBy(fn ($a) => $a->source_type . '|' . ($a->source_reference ?? $a->original_filename));

        $usedLetters = $submission->ftpAttachments()->pluck('letter')->all();

        // Force-refresh attachment-relevant relationships so we never miss
        // a B/L or invoice that was added after the declaration was first
        // hydrated by the controller.
        $declaration->load(['shipment.shippingDocuments', 'shipment.invoices', 'invoice']);

        $gathered = $this->gatherer->gather($declaration, includeMissing: true);
        $rows = [];

        foreach ($gathered as $entry) {
            $extension = $this->extractExtension($entry['originalFilename'] ?? $entry['relativePath']);
            if ($extension === null) {
                Log::warning('CAPS attachment skipped: unsupported extension', [
                    'submission_id' => $submission->id,
                    'file' => $entry['relativePath'] ?? null,
                ]);
                continue;
            }

            $key = ($entry['type'] ?? 'unknown') . '|' . ($entry['sourceReference'] ?? $entry['originalFilename'] ?? $entry['relativePath']);

            if ($existing->has($key)) {
                $attachment = $existing->get($key);
                $rows[] = [
                    'attachment' => $attachment,
                    'localPath' => $entry['filePath'],
                    'missingReason' => $entry['missingReason'],
                ];
                continue;
            }

            $letter = $this->nextAvailableLetter($usedLetters);
            if ($letter === null) {
                Log::warning('CAPS attachment skipped: 26-letter limit reached', [
                    'submission_id' => $submission->id,
                ]);
                break;
            }
            $usedLetters[] = $letter;

            $remoteFilename = $this->buildRemoteFilename($t12Filename, $letter, $extension);

            $attachment = FtpSubmissionAttachment::create([
                'web_form_submission_id' => $submission->id,
                'letter' => $letter,
                'original_filename' => $entry['originalFilename'] ?? basename($entry['relativePath']),
                'remote_filename' => $remoteFilename,
                'local_path' => $entry['relativePath'] ?? null,
                'mime_type' => $this->guessMimeType($extension),
                'size_bytes' => $entry['filePath'] && is_file($entry['filePath']) ? @filesize($entry['filePath']) : null,
                'source_type' => $entry['type'] ?? null,
                'source_reference' => $entry['sourceReference'] ?? null,
                'status' => FtpSubmissionAttachment::STATUS_PENDING,
            ]);

            $rows[] = [
                'attachment' => $attachment,
                'localPath' => $entry['filePath'],
                'missingReason' => $entry['missingReason'],
            ];
        }

        return $rows;
    }

    /**
     * Choose the next A-Z letter not already in $used.
     */
    protected function nextAvailableLetter(array $used): ?string
    {
        $usedSet = array_flip(array_map('strtoupper', $used));
        foreach (range('A', 'Z') as $letter) {
            if (!isset($usedSet[$letter])) {
                return $letter;
            }
        }
        return null;
    }

    protected function extractExtension(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }
        return $ext;
    }

    protected function guessMimeType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            default => 'application/octet-stream',
        };
    }

    protected function buildRemotePath(array $ftpSettings, string $traderId, string $remoteFilename): string
    {
        $basePath = rtrim($ftpSettings['base_path'] ?? '', '/');
        return ($basePath === '' ? '' : $basePath) . '/' . $remoteFilename;
    }

    /**
     * Parse a response file and update the matching attachment rows.
     *
     * The response file lists each uploaded filename followed by an indication
     * of whether it was accepted or rejected. We accept the simple format used
     * by CAPS today: each line that contains a letter token (` A `, `.A.`,
     * `Letter A`, etc.) is matched against our DB rows by letter; presence of
     * "REJECT" / "INVALID" / "ERROR" marks it rejected, otherwise confirmed.
     */
    protected function applyResponseFile(WebFormSubmission $submission, string $content): bool
    {
        $changed = false;
        $upper = strtoupper($content);

        $attachments = $submission->ftpAttachments()
            ->where('status', FtpSubmissionAttachment::STATUS_UPLOADED)
            ->get();

        foreach ($attachments as $attachment) {
            $remote = strtoupper($attachment->remote_filename);
            $found = false;
            $rejected = false;

            $offset = 0;
            while (($pos = strpos($upper, $remote, $offset)) !== false) {
                $found = true;
                $lineStart = strrpos(substr($upper, 0, $pos), "\n");
                $lineStart = $lineStart === false ? 0 : $lineStart + 1;
                $lineEnd = strpos($upper, "\n", $pos);
                $lineEnd = $lineEnd === false ? strlen($upper) : $lineEnd;
                $line = substr($upper, $lineStart, $lineEnd - $lineStart);

                if (preg_match('/REJECT|INVALID|ERROR|FAIL/i', $line)) {
                    $rejected = true;
                }
                $offset = $pos + strlen($remote);
            }

            if (!$found) {
                continue;
            }

            $attachment->update([
                'status' => $rejected
                    ? FtpSubmissionAttachment::STATUS_REJECTED
                    : FtpSubmissionAttachment::STATUS_CONFIRMED,
                'response_received_at' => now(),
                'caps_response_text' => $content,
                'error_message' => $rejected ? 'Rejected by CAPS — see response file' : null,
            ]);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Archive the raw response file locally for audit.
     */
    protected function archiveResponseLocally(WebFormSubmission $submission, string $filename, string $content): void
    {
        $directory = 'ftp-submissions/' . ($submission->organization_id ?? 'unknown') . '/responses';
        $stamp = Carbon::now()->format('YmdHis');
        $path = $directory . '/' . $stamp . '_' . $filename;
        try {
            Storage::disk('local')->put($path, $content);
        } catch (Throwable $e) {
            Log::warning('Failed to archive CAPS response file', [
                'submission_id' => $submission->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
