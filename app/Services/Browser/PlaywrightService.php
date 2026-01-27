<?php

namespace App\Services\Browser;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PlaywrightService - Laravel wrapper for Playwright browser automation
 * 
 * This service allows Laravel to control a headless browser through Playwright
 * for automated web form submission in production environments.
 * 
 * Supports two modes:
 * - Basic: Fast, deterministic, fails on errors
 * - AI-Assisted: Uses Claude to handle errors, find elements, interpret results
 */
class PlaywrightService
{
    protected string $scriptPath;
    protected string $aiScriptPath;
    protected string $screenshotDir;
    protected int $timeout = 120; // seconds
    protected bool $useAI = false;
    protected ?string $claudeApiKey = null;
    protected int $maxRetries = 3;

    public function __construct()
    {
        $this->scriptPath = base_path('playwright/web-form-submitter.mjs');
        $this->aiScriptPath = base_path('playwright/ai-web-form-submitter.mjs');
        $this->screenshotDir = storage_path('app/playwright-screenshots');
        $this->claudeApiKey = config('services.claude.api_key') ?? env('CLAUDE_API_KEY') ?? env('ANTHROPIC_API_KEY');
        
        // Ensure screenshot directory exists
        if (!is_dir($this->screenshotDir)) {
            mkdir($this->screenshotDir, 0755, true);
        }
    }

    /**
     * Enable AI-assisted mode for error recovery
     */
    public function withAI(bool $enabled = true): self
    {
        $this->useAI = $enabled;
        if ($enabled) {
            $this->timeout = 180; // AI mode needs more time
        }
        return $this;
    }

    /**
     * Set maximum retry attempts for AI mode
     */
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $retries;
        return $this;
    }

    /**
     * Test connectivity to a target URL
     */
    public function testConnection(string $baseUrl, array $credentials = []): array
    {
        return $this->execute([
            'action' => 'test',
            'baseUrl' => $baseUrl,
            'credentials' => $credentials,
            'headless' => true,
        ]);
    }

    /**
     * Submit a form with the given data
     */
    public function submitForm(
        string $baseUrl,
        array $formData,
        array $credentials = [],
        bool $headless = true
    ): array {
        return $this->execute([
            'action' => 'submit',
            'baseUrl' => $baseUrl,
            'data' => $formData,
            'credentials' => $credentials,
            'headless' => $headless,
        ]);
    }

    /**
     * Submit a form with AI assistance for error recovery
     */
    public function submitFormWithAI(
        string $baseUrl,
        array $formData,
        array $credentials = [],
        bool $headless = true
    ): array {
        return $this->withAI(true)->submitForm($baseUrl, $formData, $credentials, $headless);
    }

    /**
     * Execute the Playwright script with given parameters
     */
    protected function execute(array $params): array
    {
        $params['screenshotDir'] = $this->screenshotDir;
        
        // Add AI parameters if enabled
        if ($this->useAI) {
            $params['claudeApiKey'] = $this->claudeApiKey;
            $params['maxRetries'] = $this->maxRetries;
        }
        
        Log::info('PlaywrightService: Starting execution', [
            'action' => $params['action'],
            'baseUrl' => $params['baseUrl'],
            'ai_enabled' => $this->useAI,
        ]);
        
        $startTime = microtime(true);
        
        // Choose script based on AI mode
        $scriptPath = $this->useAI ? $this->aiScriptPath : $this->scriptPath;
        
        // Write params to temp file (avoids shell escaping issues)
        $tempFile = storage_path('app/playwright-input-' . uniqid() . '.json');
        file_put_contents($tempFile, json_encode($params, JSON_PRETTY_PRINT));
        
        try {
            $result = Process::timeout($this->timeout)
                ->run("node \"{$scriptPath}\" --input-file=\"{$tempFile}\"");
            
            // Clean up temp file
            @unlink($tempFile);
            
            $duration = round(microtime(true) - $startTime, 2);
            
            // Parse the JSON output
            $output = $result->output();
            $stderr = $result->errorOutput();
            
            $resultData = json_decode($output, true);
            
            if (!$resultData) {
                Log::error('PlaywrightService: Failed to parse output', [
                    'output' => $output,
                    'stderr' => $stderr,
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Failed to parse Playwright output',
                    'raw_output' => $output,
                    'raw_stderr' => $stderr,
                    'duration_seconds' => $duration,
                ];
            }
            
            $resultData['duration_seconds'] = $duration;
            
            Log::info('PlaywrightService: Execution complete', [
                'success' => $resultData['success'],
                'duration' => $duration,
                'reference' => $resultData['reference_number'] ?? null,
            ]);
            
            return $resultData;
            
        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            Log::error('PlaywrightService: Execution failed', [
                'error' => $e->getMessage(),
                'duration' => $duration,
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration_seconds' => $duration,
            ];
        }
    }

    /**
     * Set the timeout for script execution
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Get the screenshot directory path
     */
    public function getScreenshotDir(): string
    {
        return $this->screenshotDir;
    }

    /**
     * Check if Playwright/Node.js is available
     */
    public function isAvailable(): bool
    {
        try {
            $result = Process::run('node --version');
            return $result->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Node.js version
     */
    public function getNodeVersion(): ?string
    {
        try {
            $result = Process::run('node --version');
            return trim($result->output());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if AI mode is enabled
     */
    public function isAIEnabled(): bool
    {
        return $this->useAI;
    }

    /**
     * Get the AI script path
     */
    public function getAIScriptPath(): string
    {
        return $this->aiScriptPath;
    }

    /**
     * Get the basic script path
     */
    public function getScriptPath(): string
    {
        return $this->scriptPath;
    }
}
