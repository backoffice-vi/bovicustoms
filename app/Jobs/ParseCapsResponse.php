<?php

namespace App\Jobs;

use App\Models\WebFormSubmission;
use App\Services\FtpSubmission\CapsResponseParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Parse a CAPS query report PDF/TXT in the background.
 *
 * Triggered after a broker uploads a query report through the result page.
 * Runs CapsResponseParser (which calls Claude with the PDF) and flips the
 * submission's caps_response_status from "parsing" to "rejected" /
 * "accepted" / "partial" / "parse_failed".
 *
 * Designed to be dispatched via dispatchAfterResponse() in dev (sync queue)
 * or onto a real queue worker in production. Either way, the upload POST
 * returns immediately so the broker never sees a stuck page.
 */
class ParseCapsResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Claude PDF parsing of large query reports can take 4–5 minutes. */
    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public int $submissionId,
        public string $storedPath,
        public string $source = WebFormSubmission::CAPS_SOURCE_MANUAL_UPLOAD,
    ) {
    }

    public function handle(CapsResponseParser $parser): void
    {
        $submission = WebFormSubmission::find($this->submissionId);

        if (!$submission) {
            Log::warning('ParseCapsResponse: submission missing', [
                'submission_id' => $this->submissionId,
            ]);
            return;
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($this->storedPath)) {
            $submission->markCapsParseFailed(
                "Uploaded report file no longer exists at {$this->storedPath}"
            );
            return;
        }

        $binary = $disk->get($this->storedPath);
        $extension = strtolower(pathinfo($this->storedPath, PATHINFO_EXTENSION));

        try {
            $parsed = $extension === 'pdf'
                ? $parser->parsePdf((string) $binary)
                : $parser->parseText((string) $binary);
        } catch (\Throwable $e) {
            Log::error('ParseCapsResponse: parser threw', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            $submission->markCapsParseFailed($e->getMessage());
            return;
        }

        $overall = $parsed['overall_status'] ?? 'rejected';

        if ($overall === 'accepted') {
            $submission->markCapsAccepted($this->source, $this->storedPath);
            Log::info('ParseCapsResponse: marked accepted', [
                'submission_id' => $submission->id,
            ]);
            return;
        }

        $submission->markCapsRejected(
            $parsed,
            $this->source,
            $this->storedPath,
            $overall === 'partial'
        );

        Log::info('ParseCapsResponse: marked rejected', [
            'submission_id' => $submission->id,
            'errors' => count($parsed['errors'] ?? []),
            'overall' => $overall,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $submission = WebFormSubmission::find($this->submissionId);
        if ($submission && $submission->is_caps_parsing) {
            $submission->markCapsParseFailed('Parser job failed: ' . $e->getMessage());
        }

        Log::error('ParseCapsResponse: job failed', [
            'submission_id' => $this->submissionId,
            'error' => $e->getMessage(),
        ]);
    }
}
