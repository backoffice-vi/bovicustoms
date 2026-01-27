<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class EnsureQueueWorker extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:ensure-worker 
                            {--timeout=900 : Maximum seconds the worker should run}
                            {--memory=512 : Memory limit in MB}';

    /**
     * The console command description.
     */
    protected $description = 'Ensure a queue worker is running, starting one if necessary';

    /**
     * Timeout value for programmatic invocation.
     */
    protected ?int $manualTimeout = null;

    /**
     * Memory value for programmatic invocation.
     */
    protected ?int $manualMemory = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if a worker is already running
        if ($this->isWorkerRunning()) {
            $this->info('Queue worker is already running.');
            return Command::SUCCESS;
        }

        $this->info('No queue worker detected. Starting one...');
        
        // Start worker in the background
        $this->startBackgroundWorker();
        
        $this->info('Queue worker started successfully.');
        
        return Command::SUCCESS;
    }

    /**
     * Check if a queue worker process is already running.
     */
    protected function isWorkerRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: Use PowerShell to check for queue:work processes
            $psCommand = 'powershell -Command "Get-CimInstance Win32_Process -Filter \"name=\'php.exe\'\" | Select-Object CommandLine | Out-String"';
            $result = Process::run($psCommand);
            
            if ($result->successful() && str_contains($result->output(), 'queue:work')) {
                \Illuminate\Support\Facades\Log::debug('Queue worker detected as running');
                return true;
            }
            
            \Illuminate\Support\Facades\Log::debug('No queue worker detected');
            return false;
        }
        
        // Unix/Linux/Mac: Use pgrep
        $result = Process::run('pgrep -f "artisan queue:work"');
        
        return $result->successful() && !empty(trim($result->output()));
    }

    /**
     * Start a queue worker in the background.
     */
    protected function startBackgroundWorker(): void
    {
        // Use manual values if set (programmatic invocation), otherwise use console options
        $timeout = $this->manualTimeout ?? $this->option('timeout');
        $memory = $this->manualMemory ?? $this->option('memory');
        $phpBinary = PHP_BINARY;
        $artisan = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: Use WScript.Shell for reliable background execution
            $command = sprintf(
                '"%s" "%s" queue:work --stop-when-empty --timeout=%d --memory=%d --tries=3',
                $phpBinary,
                $artisan,
                $timeout,
                $memory
            );
            
            \Illuminate\Support\Facades\Log::info('Starting queue worker', ['command' => $command]);
            
            // Create a WScript.Shell COM object to run invisibly
            if (class_exists('COM')) {
                try {
                    $shell = new \COM('WScript.Shell');
                    $shell->Run($command, 0, false); // 0 = hidden, false = don't wait
                    \Illuminate\Support\Facades\Log::info('Queue worker started via COM');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('COM failed, using popen fallback', ['error' => $e->getMessage()]);
                    // Fallback to popen
                    pclose(popen('start /B "" ' . $command, 'r'));
                }
            } else {
                // Fallback if COM is not available
                \Illuminate\Support\Facades\Log::info('COM not available, using popen');
                pclose(popen('start /B "" ' . $command, 'r'));
            }
        } else {
            // Unix/Linux/Mac: Use nohup with output redirection
            $command = sprintf(
                '%s %s queue:work --stop-when-empty --timeout=%d --memory=%d --tries=3',
                escapeshellarg($phpBinary),
                escapeshellarg($artisan),
                $timeout,
                $memory
            );
            
            $logFile = storage_path('logs/queue-worker.log');
            $fullCommand = sprintf('nohup %s >> %s 2>&1 &', $command, escapeshellarg($logFile));
            exec($fullCommand);
        }
    }

    /**
     * Start a worker and return immediately (static method for use in controllers).
     */
    public static function ensureRunning(int $timeout = 900, int $memory = 512): void
    {
        $instance = new static();
        $instance->setLaravel(app());
        
        // Set values directly as properties for programmatic invocation
        $instance->manualTimeout = $timeout;
        $instance->manualMemory = $memory;
        
        // Only start if not already running
        if (!$instance->isWorkerRunning()) {
            $instance->startBackgroundWorker();
            
            \Illuminate\Support\Facades\Log::info('Queue worker started automatically for job processing');
        }
    }
}
