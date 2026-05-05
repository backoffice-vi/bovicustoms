<?php

namespace App\Services\FtpSubmission;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Shared FTP plumbing used by FtpSubmissionService (T12 upload) and
 * CapsAttachmentUploader (attachment upload + response polling).
 *
 * Consumers must hold the connection on `$this->connection` and call
 * `connect()` -> work -> `disconnect()`.
 */
trait FtpConnection
{
    /** @var resource|\FTP\Connection|null */
    protected $connection = null;

    protected function connect(array $ftpSettings, array $credentials): void
    {
        $host = $ftpSettings['host'];
        $port = $ftpSettings['port'] ?? 21;
        $passive = $ftpSettings['passive'] ?? true;

        $this->connection = ftp_connect($host, $port, 30);
        if (!$this->connection) {
            throw new RuntimeException("Could not connect to FTP server: {$host}:{$port}");
        }

        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        if (!ftp_login($this->connection, $username, $password)) {
            ftp_close($this->connection);
            $this->connection = null;
            throw new RuntimeException("FTP login failed for user: {$username}");
        }

        if ($passive) {
            ftp_pasv($this->connection, true);
        }

        Log::debug('FTP connected', ['host' => $host, 'user' => $username]);
    }

    protected function disconnect(): void
    {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Upload string content to a remote path. Mode is FTP_ASCII for T12
     * text files, FTP_BINARY for attachments (PDF, images).
     */
    protected function upload(string $content, string $remotePath, int $mode = FTP_ASCII): void
    {
        if (!$this->connection) {
            throw new RuntimeException('Not connected to FTP server');
        }

        $tempFile = tmpfile();
        if ($tempFile === false) {
            throw new RuntimeException('Could not create temporary file for upload');
        }

        fwrite($tempFile, $content);
        rewind($tempFile);

        $tempMeta = stream_get_meta_data($tempFile);
        $tempPath = $tempMeta['uri'];

        $this->ensureDirectoryExists(dirname($remotePath));

        $result = ftp_put($this->connection, $remotePath, $tempPath, $mode);
        fclose($tempFile);

        if (!$result) {
            throw new RuntimeException("Failed to upload file to: {$remotePath}");
        }

        Log::debug('FTP upload successful', ['remote_path' => $remotePath, 'mode' => $mode]);
    }

    /**
     * Upload a local file (used for binary attachments).
     */
    protected function uploadLocalFile(string $localPath, string $remotePath, int $mode = FTP_BINARY): void
    {
        if (!$this->connection) {
            throw new RuntimeException('Not connected to FTP server');
        }

        if (!is_file($localPath)) {
            throw new RuntimeException("Local file not found: {$localPath}");
        }

        $this->ensureDirectoryExists(dirname($remotePath));

        $result = ftp_put($this->connection, $remotePath, $localPath, $mode);
        if (!$result) {
            throw new RuntimeException("Failed to upload file to: {$remotePath}");
        }

        Log::debug('FTP upload successful', ['remote_path' => $remotePath, 'mode' => $mode]);
    }

    /**
     * Download a remote file to a string.
     */
    protected function downloadToString(string $remotePath, int $mode = FTP_ASCII): ?string
    {
        if (!$this->connection) {
            throw new RuntimeException('Not connected to FTP server');
        }

        $tempFile = tmpfile();
        if ($tempFile === false) {
            return null;
        }

        $tempMeta = stream_get_meta_data($tempFile);
        $tempPath = $tempMeta['uri'];

        if (!@ftp_get($this->connection, $tempPath, $remotePath, $mode)) {
            fclose($tempFile);
            return null;
        }

        rewind($tempFile);
        $content = stream_get_contents($tempFile);
        fclose($tempFile);

        return $content === false ? null : $content;
    }

    /**
     * List filenames in a remote directory. Returns just the basenames.
     *
     * @return array<int, string>
     */
    protected function listRemoteFiles(string $directory): array
    {
        if (!$this->connection) {
            throw new RuntimeException('Not connected to FTP server');
        }

        $items = @ftp_nlist($this->connection, $directory ?: '.');
        if ($items === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($p) => basename((string) $p),
            $items
        )));
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        if (empty($directory) || $directory === '/' || $directory === '.') {
            return;
        }

        if (@ftp_chdir($this->connection, $directory)) {
            @ftp_chdir($this->connection, '/');
            return;
        }

        $parts = explode('/', trim($directory, '/'));
        $currentPath = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $currentPath .= '/' . $part;

            if (!@ftp_chdir($this->connection, $currentPath)) {
                if (!@ftp_mkdir($this->connection, $currentPath)) {
                    if (!@ftp_chdir($this->connection, $currentPath)) {
                        throw new RuntimeException("Could not create directory: {$currentPath}");
                    }
                }
            }
        }

        @ftp_chdir($this->connection, '/');
    }
}
