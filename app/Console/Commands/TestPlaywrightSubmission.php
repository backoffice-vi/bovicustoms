<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class TestPlaywrightSubmission extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'playwright:test 
                            {--action=test : Action to perform (test or submit)}
                            {--headless : Run in headless mode (no browser window)}
                            {--ai : Use AI-assisted submission with error recovery}
                            {--url= : Base URL of the application}';

    /**
     * The console command description.
     */
    protected $description = 'Test Playwright browser automation for web form submission';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->option('action');
        $headless = $this->option('headless');
        $useAI = $this->option('ai');
        $baseUrl = $this->option('url') ?? 'http://127.0.0.1:8010';

        $this->info('🎭 Playwright Web Form Automation Test');
        $this->line('=====================================');
        $this->line("Action: {$action}");
        $this->line("Base URL: {$baseUrl}");
        $this->line("Headless: " . ($headless ? 'Yes' : 'No'));
        $this->line("AI Assisted: " . ($useAI ? 'Yes' : 'No'));
        $this->newLine();

        // Ensure screenshot directory exists
        $screenshotDir = storage_path('app/playwright-screenshots');
        if (!is_dir($screenshotDir)) {
            mkdir($screenshotDir, 0755, true);
        }

        // Build the input JSON
        $input = [
            'action' => $action,
            'baseUrl' => $baseUrl,
            'headless' => $headless,
            'screenshotDir' => $screenshotDir,
            'credentials' => [
                'username' => 'testuser',
                'password' => 'testpass123',
            ],
        ];

        // Add Claude API key for AI-assisted mode
        if ($useAI) {
            $input['claudeApiKey'] = config('services.claude.api_key') ?? env('CLAUDE_API_KEY') ?? env('ANTHROPIC_API_KEY');
            $input['maxRetries'] = 3;
            
            if (empty($input['claudeApiKey'])) {
                $this->warn('⚠️  No Claude API key found - AI assistance will be limited');
            }
        }

        // Add sample data for submission
        if ($action === 'submit') {
            $input['data'] = $this->getSampleDeclarationData();
        }

        // Choose script based on AI mode
        $scriptPath = $useAI 
            ? base_path('playwright/ai-web-form-submitter.mjs')
            : base_path('playwright/web-form-submitter.mjs');

        // Write input to a temp file to avoid shell escaping issues
        $tempFile = storage_path('app/playwright-input-' . uniqid() . '.json');
        file_put_contents($tempFile, json_encode($input, JSON_PRETTY_PRINT));

        $this->info('Starting Playwright...');
        $this->newLine();

        // Run the Node.js script with input file (longer timeout for AI)
        $timeout = $useAI ? 180 : 120;
        $result = Process::timeout($timeout)->run("node \"{$scriptPath}\" --input-file=\"{$tempFile}\"");

        // Clean up temp file
        @unlink($tempFile);

        // Parse the output
        $output = $result->output();
        $stderr = $result->errorOutput();

        // Show logs from stderr
        if (!empty($stderr)) {
            $this->line('<fg=gray>--- Playwright Logs ---</>');
            foreach (explode("\n", trim($stderr)) as $line) {
                if (!empty($line)) {
                    $this->line("<fg=gray>{$line}</>");
                }
            }
            $this->newLine();
        }

        // Parse JSON result from stdout
        $resultData = null;
        if (!empty($output)) {
            $resultData = json_decode($output, true);
        }

        if ($resultData) {
            $this->displayResult($resultData);
        } else {
            $this->error('Failed to parse Playwright output');
            $this->line($output);
            return Command::FAILURE;
        }

        return $resultData['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Display the result in a formatted way
     */
    protected function displayResult(array $result): void
    {
        $this->line('--- Result ---');
        $this->newLine();

        if ($result['success']) {
            $this->info('✅ ' . ($result['message'] ?? 'Success!'));
        } else {
            $this->error('❌ ' . ($result['error'] ?? 'Unknown error'));
        }

        if (!empty($result['reference_number'])) {
            $this->newLine();
            $this->info("📋 Reference Number: {$result['reference_number']}");
        }

        // Show AI decisions if any
        if (!empty($result['ai_decisions'])) {
            $this->newLine();
            $this->line('🤖 AI Decisions:');
            foreach ($result['ai_decisions'] as $decision) {
                $this->line("   • {$decision['situation']}");
                $this->line("     → Action: {$decision['decision']}");
                $this->line("     → Reason: {$decision['reasoning']}");
            }
        }

        // Show errors that were handled
        if (!empty($result['errors_handled'])) {
            $this->newLine();
            $this->line('🔧 Errors Recovered:');
            foreach ($result['errors_handled'] as $handled) {
                $this->line("   • {$handled['error']}");
                $this->line("     → Recovery: {$handled['decision']['action']}");
            }
        }

        if (!empty($result['screenshots'])) {
            $this->newLine();
            $this->line('📸 Screenshots saved:');
            foreach ($result['screenshots'] as $screenshot) {
                $this->line("   - {$screenshot}");
            }
        }

        $this->newLine();
    }

    /**
     * Get sample declaration data for testing
     */
    protected function getSampleDeclarationData(): array
    {
        return [
            // Shipment Info
            'vessel_name' => 'MSC LORENA',
            'voyage_number' => 'VY-2026-001',
            'bill_of_lading' => 'MSCUAB123456789',
            'manifest_number' => 'MN-2026-00123',
            'port_of_loading' => 'USMIA', // Miami
            'arrival_date' => date('Y-m-d', strtotime('+3 days')),

            // Shipper
            'shipper_name' => 'Global Trading Co.',
            'shipper_country' => 'US',
            'shipper_address' => '123 Export Street, Miami, FL 33131',

            // Consignee
            'consignee_name' => "Nature's Way Ltd.",
            'consignee_id' => 'BVI-NW-001',

            // Goods
            'hs_code' => '8471.30',
            'country_of_origin' => 'US',
            'goods_description' => 'Portable digital automatic data processing machines weighing not more than 10 kg, consisting of at least a central processing unit, a keyboard and a display',
            'quantity' => 50,
            'gross_weight' => 125.5,
            'total_packages' => 10,

            // Values
            'fob_value' => 25000.00,
            'freight_value' => 1500.00,
            'insurance_value' => 265.00,
        ];
    }
}
