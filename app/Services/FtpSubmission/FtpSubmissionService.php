<?php

namespace App\Services\FtpSubmission;

use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for handling FTP submission of T12 files to CAPS
 */
class FtpSubmissionService
{
    protected CapsT12Generator $generator;
    protected $connection = null;

    public function __construct(CapsT12Generator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Submit a declaration via FTP
     */
    public function submit(
        DeclarationForm $declaration,
        OrganizationSubmissionCredential $credentials,
        bool $saveLocally = true
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
     * Connect to FTP server
     */
    protected function connect(array $ftpSettings, array $credentials): void
    {
        $host = $ftpSettings['host'];
        $port = $ftpSettings['port'] ?? 21;
        $passive = $ftpSettings['passive'] ?? true;

        // Establish connection
        $this->connection = ftp_connect($host, $port, 30);
        
        if (!$this->connection) {
            throw new \RuntimeException("Could not connect to FTP server: {$host}:{$port}");
        }

        // Login
        $username = $credentials['username'];
        $password = $credentials['password'];

        if (!ftp_login($this->connection, $username, $password)) {
            ftp_close($this->connection);
            $this->connection = null;
            throw new \RuntimeException("FTP login failed for user: {$username}");
        }

        // Set passive mode
        if ($passive) {
            ftp_pasv($this->connection, true);
        }

        Log::debug('FTP connected', ['host' => $host, 'user' => $username]);
    }

    /**
     * Disconnect from FTP server
     */
    protected function disconnect(): void
    {
        if ($this->connection) {
            ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Upload content to FTP server
     */
    protected function upload(string $content, string $remotePath): void
    {
        if (!$this->connection) {
            throw new \RuntimeException('Not connected to FTP server');
        }

        // Create a temporary file
        $tempFile = tmpfile();
        fwrite($tempFile, $content);
        rewind($tempFile);
        
        $tempMeta = stream_get_meta_data($tempFile);
        $tempPath = $tempMeta['uri'];

        // Ensure directory exists
        $remoteDir = dirname($remotePath);
        $this->ensureDirectoryExists($remoteDir);

        // Upload the file
        $result = ftp_put($this->connection, $remotePath, $tempPath, FTP_ASCII);
        
        fclose($tempFile);

        if (!$result) {
            throw new \RuntimeException("Failed to upload file to: {$remotePath}");
        }

        Log::debug('FTP upload successful', ['remote_path' => $remotePath]);
    }

    /**
     * Ensure remote directory exists
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (empty($directory) || $directory === '/') {
            return;
        }

        // Try to change to the directory
        if (@ftp_chdir($this->connection, $directory)) {
            // Directory exists, change back to root
            ftp_chdir($this->connection, '/');
            return;
        }

        // Directory doesn't exist, create it
        $parts = explode('/', trim($directory, '/'));
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            
            if (!@ftp_chdir($this->connection, $currentPath)) {
                if (!@ftp_mkdir($this->connection, $currentPath)) {
                    // Directory might already exist due to race condition, try again
                    if (!@ftp_chdir($this->connection, $currentPath)) {
                        throw new \RuntimeException("Could not create directory: {$currentPath}");
                    }
                }
            }
        }

        // Return to root
        ftp_chdir($this->connection, '/');
    }

    /**
     * Get the remote path for the file
     */
    protected function getRemotePath(array $ftpSettings, string $traderId, string $filename): string
    {
        $basePath = rtrim($ftpSettings['base_path'] ?? '', '/');
        
        // CAPS expects files in trader-specific directories
        return "{$basePath}/{$traderId}/{$filename}";
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
