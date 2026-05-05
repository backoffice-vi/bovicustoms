<?php

namespace App\Services\FtpSubmission;

use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
use App\Services\WebFormSubmission\CapsPreValidationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for handling FTP submission of T12 files to CAPS
 */
class FtpSubmissionService
{
    use FtpConnection;

    protected CapsT12Generator $generator;
    protected CapsPreValidationService $capsPreValidation;
    protected CapsAttachmentUploader $attachmentUploader;

    public function __construct(
        CapsT12Generator $generator,
        CapsPreValidationService $capsPreValidation,
        CapsAttachmentUploader $attachmentUploader
    ) {
        $this->generator = $generator;
        $this->capsPreValidation = $capsPreValidation;
        $this->attachmentUploader = $attachmentUploader;
    }

    /**
     * Submit a declaration via FTP.
     *
     * @param bool $autoAttach when true, attachments (B/L, invoices) are uploaded
     *                         immediately after the T12 file. Driven by the UI
     *                         "Also upload attachments now" checkbox. Default
     *                         is false to avoid silently changing existing
     *                         callers (admin test page, etc).
     */
    public function submit(
        DeclarationForm $declaration,
        OrganizationSubmissionCredential $credentials,
        bool $saveLocally = true,
        bool $autoAttach = false
    ): WebFormSubmission {
        $declaration->load(['country', 'organization']);
        
        $country = $declaration->country;
        
        if (!$country || !$country->isFtpEnabled()) {
            throw new \RuntimeException('FTP submission is not enabled for this country');
        }

        if (!$credentials->hasCompleteFtpCredentials()) {
            throw new \RuntimeException('FTP credentials are incomplete');
        }

        // Generate the T12 file
        $t12Data = $this->generator->generate($declaration, $credentials);
        $preValidation = $this->capsPreValidation->validateT12Content($t12Data['content'], $declaration->country_id);

        if (!$preValidation['valid']) {
            throw new \RuntimeException('CAPS T12 pre-validation failed: ' . implode('; ', array_slice($preValidation['errors'], 0, 8)));
        }
        
        // Create submission record
        $submission = WebFormSubmission::create([
            'declaration_form_id' => $declaration->id,
            'web_form_target_id' => null, // FTP submission, no web target
            'user_id' => auth()->id(),
            'submission_type' => 'ftp',
            'status' => 'pending',
            'submitted_at' => now(),
            'request_data' => [
                'filename' => $t12Data['filename'],
                'trader_id' => $t12Data['trader_id'],
                'line_count' => $t12Data['line_count'],
                'item_count' => $t12Data['item_count'],
                'caps_pre_validation' => $preValidation,
            ],
        ]);

        try {
            // Save locally first
            if ($saveLocally) {
                $localPath = $this->saveLocally($t12Data, $declaration);
                $submission->update([
                    'request_data' => array_merge($submission->request_data ?? [], [
                        'local_path' => $localPath,
                    ]),
                ]);
            }

            // Get FTP settings from country
            $ftpSettings = $country->getFtpSettings();
            $ftpCreds = $credentials->getFtpCredentials();

            // Connect and upload
            $this->connect($ftpSettings, $ftpCreds);
            
            $remotePath = $this->getRemotePath($ftpSettings, $ftpCreds['trader_id'], $t12Data['filename']);
            
            $this->upload($t12Data['content'], $remotePath);
            
            $this->disconnect();

            // Mark credentials as used
            $credentials->markUsed();

            // Update submission as successful
            $submission->update([
                'status' => 'submitted',
                'is_successful' => true,
                'external_reference' => $t12Data['filename'],
                'response_data' => [
                    'remote_path' => $remotePath,
                    'uploaded_at' => now()->toIso8601String(),
                    'file_size' => strlen($t12Data['content']),
                ],
            ]);

            // Update declaration status
            $declaration->update([
                'submission_status' => DeclarationForm::SUBMISSION_STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by_user_id' => auth()->id(),
                'submission_reference' => $t12Data['filename'],
                'submission_notes' => 'Submitted via FTP to CAPS',
            ]);

            Log::info('FTP submission successful', [
                'declaration_id' => $declaration->id,
                'submission_id' => $submission->id,
                'filename' => $t12Data['filename'],
                'remote_path' => $remotePath,
            ]);

            // Optionally upload attachments alongside the T12 (Spec 4.0 §1.9)
            if ($autoAttach) {
                try {
                    $this->attachmentUploader->uploadForSubmission($submission, $declaration, $country, $credentials);
                } catch (\Throwable $e) {
                    Log::warning('FTP attachment upload failed (T12 upload still succeeded)', [
                        'submission_id' => $submission->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $submission;

        } catch (\Exception $e) {
            $this->disconnect();

            $submission->update([
                'status' => 'failed',
                'is_successful' => false,
                'error_message' => $e->getMessage(),
                'response_data' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            Log::error('FTP submission failed', [
                'declaration_id' => $declaration->id,
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Manually trigger an attachment upload for an existing T12 submission.
     * Used by the "Upload Attachments via FTP" button on the declaration page.
     */
    public function uploadAttachmentsOnly(
        WebFormSubmission $submission,
        DeclarationForm $declaration,
        OrganizationSubmissionCredential $credentials
    ): array {
        $declaration->loadMissing('country');
        $country = $declaration->country;

        if (!$country || !$country->isFtpEnabled()) {
            throw new \RuntimeException('FTP submission is not enabled for this country');
        }

        if (!$credentials->hasCompleteFtpCredentials()) {
            throw new \RuntimeException('FTP credentials are incomplete');
        }

        return $this->attachmentUploader->uploadForSubmission($submission, $declaration, $country, $credentials);
    }

    /**
     * Test FTP connection with given credentials
     */
    public function testConnection(Country $country, OrganizationSubmissionCredential $credentials): array
    {
        if (!$country->isFtpEnabled()) {
            return [
                'success' => false,
                'message' => 'FTP is not enabled for this country',
            ];
        }

        if (!$credentials->hasCompleteFtpCredentials()) {
            return [
                'success' => false,
                'message' => 'FTP credentials are incomplete',
            ];
        }

        try {
            $ftpSettings = $country->getFtpSettings();
            $ftpCreds = $credentials->getFtpCredentials();

            $this->connect($ftpSettings, $ftpCreds);
            
            // Try to list directory to verify connection
            $currentDir = ftp_pwd($this->connection);
            
            $this->disconnect();

            $credentials->markTested();

            return [
                'success' => true,
                'message' => 'Connection successful',
                'details' => [
                    'host' => $ftpSettings['host'],
                    'current_directory' => $currentDir,
                ],
            ];

        } catch (\Exception $e) {
            $this->disconnect();

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the remote path for the file
     */
    protected function getRemotePath(array $ftpSettings, string $traderId, string $filename): string
    {
        $basePath = rtrim($ftpSettings['base_path'] ?? '', '/');
        
        // Upload directly to the base path (root)
        return "{$basePath}/{$filename}";
    }

    /**
     * Save T12 file locally
     */
    protected function saveLocally(array $t12Data, DeclarationForm $declaration): string
    {
        $directory = 'ftp-submissions/' . $declaration->organization_id;
        $filename = $t12Data['filename'];
        $path = "{$directory}/{$filename}";

        Storage::disk('local')->put($path, $t12Data['content']);

        return $path;
    }

    /**
     * Download a locally saved T12 file
     */
    public function downloadLocal(string $path): ?string
    {
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->get($path);
        }

        return null;
    }

    /**
     * Generate T12 content without uploading (for preview/download)
     */
    public function generateOnly(
        DeclarationForm $declaration,
        OrganizationSubmissionCredential $credentials
    ): array {
        return $this->generator->generate($declaration, $credentials);
    }

    /**
     * Preview T12 content in structured format
     */
    public function preview(
        DeclarationForm $declaration,
        OrganizationSubmissionCredential $credentials
    ): array {
        return $this->generator->preview($declaration, $credentials);
    }

    public function validatePreview(array $preview, DeclarationForm $declaration): array
    {
        return $this->capsPreValidation->validateT12Preview($preview, $declaration->country_id);
    }

    /**
     * Validate declaration data for T12 generation
     */
    public function validate(DeclarationForm $declaration): array
    {
        $errors = [];
        $warnings = [];

        // Check required fields
        if (empty($declaration->arrival_date)) {
            $warnings[] = 'Arrival date is missing';
        }

        if (empty($declaration->bill_of_lading_number) && empty($declaration->awb_number)) {
            $warnings[] = 'Bill of Lading or AWB number is missing';
        }

        if (empty($declaration->total_packages) || $declaration->total_packages < 1) {
            $warnings[] = 'Total packages is missing or zero';
        }

        // Check items
        $declaration->load(['declarationItems', 'invoice.invoiceItems']);
        
        $hasItems = ($declaration->declarationItems && $declaration->declarationItems->count() > 0)
            || ($declaration->invoice && $declaration->invoice->invoiceItems && $declaration->invoice->invoiceItems->count() > 0)
            || (!empty($declaration->items) && is_array($declaration->items) && count($declaration->items) > 0);

        if (!$hasItems) {
            $errors[] = 'No items found in declaration';
        }

        // Check shipper/consignee
        if (!$declaration->shipperContact && !$declaration->shipment?->shipperContact) {
            $warnings[] = 'Shipper contact information is missing';
        }

        if (!$declaration->consigneeContact && !$declaration->shipment?->consigneeContact) {
            $warnings[] = 'Consignee contact information is missing';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
