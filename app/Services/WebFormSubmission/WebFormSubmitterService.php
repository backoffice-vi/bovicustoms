<?php

namespace App\Services\WebFormSubmission;

use App\Models\DeclarationForm;
use App\Models\WebFormTarget;
use App\Models\WebFormSubmission;
use App\Services\Browser\PlaywrightService;
use Illuminate\Support\Facades\Log;

/**
 * WebFormSubmitterService
 * 
 * Orchestrates the submission of declaration forms to external web portals
 * using Playwright browser automation with optional AI assistance.
 */
class WebFormSubmitterService
{
    protected PlaywrightService $playwright;
    protected WebFormDataMapper $dataMapper;
    protected CapsAIMapper $aiMapper;

    public function __construct(
        PlaywrightService $playwright, 
        WebFormDataMapper $dataMapper,
        CapsAIMapper $aiMapper
    ) {
        $this->playwright = $playwright;
        $this->dataMapper = $dataMapper;
        $this->aiMapper = $aiMapper;
    }

    /**
     * Submit a declaration to an external web portal
     */
    public function submit(
        DeclarationForm $declaration,
        WebFormTarget $target,
        bool $useAI = true
    ): WebFormSubmission {
        // Create submission record
        $submission = WebFormSubmission::create([
            'web_form_target_id' => $target->id,
            'declaration_form_id' => $declaration->id,
            'user_id' => auth()->id(),
            'organization_id' => $declaration->organization_id,
            'status' => WebFormSubmission::STATUS_PENDING,
        ]);

        try {
            // Start submission
            $submission->start();
            $submission->addLog('Starting submission to ' . $target->name);

            // Map declaration data to web form fields
            $mappedData = $this->dataMapper->mapDeclarationToTarget($declaration, $target);
            $submission->setMappedData($mappedData);
            $submission->addLog('Mapped ' . count($mappedData['fields']) . ' fields');

            // Get credentials
            $credentials = $target->getPlaywrightCredentials();

            // Configure Playwright
            if ($useAI || $target->requires_ai) {
                $this->playwright->withAI(true);
            }

            // Build the input for Playwright
            $playwrightInput = $this->buildPlaywrightInput($target, $mappedData, $credentials);

            // Execute submission
            $submission->addLog('Executing Playwright automation');
            $result = $this->executePlaywright($playwrightInput, $submission);

            // Process result
            if ($result['success']) {
                $submission->markSubmitted(
                    $result['reference_number'] ?? null,
                    $result['message'] ?? null
                );
                $submission->addLog('Submission successful', 'success');

                // Update declaration status
                $declaration->update([
                    'submission_status' => DeclarationForm::SUBMISSION_STATUS_SUBMITTED,
                    'submission_reference' => $result['reference_number'],
                    'submitted_at' => now(),
                    'submitted_by_user_id' => auth()->id(),
                ]);
            } else {
                $submission->markFailed(
                    $result['error'] ?? 'Unknown error',
                    $result['errors_handled'] ?? null
                );
                $submission->addLog('Submission failed: ' . ($result['error'] ?? 'Unknown error'), 'error');
            }

            // Store AI decisions if any
            if (!empty($result['ai_decisions'])) {
                foreach ($result['ai_decisions'] as $decision) {
                    $submission->addAiDecision(
                        $decision['situation'] ?? '',
                        $decision['decision'] ?? '',
                        $decision['reasoning'] ?? ''
                    );
                }
            }

            // Store screenshots
            if (!empty($result['screenshots'])) {
                foreach ($result['screenshots'] as $screenshot) {
                    $submission->addScreenshot($screenshot);
                }
            }

        } catch (\Exception $e) {
            Log::error('WebFormSubmission failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            $submission->markFailed($e->getMessage());
            $submission->addLog('Exception: ' . $e->getMessage(), 'error');
        }

        return $submission->fresh();
    }

    /**
     * Retry a failed submission
     */
    public function retry(WebFormSubmission $submission): WebFormSubmission
    {
        if (!$submission->can_retry) {
            throw new \Exception('This submission cannot be retried (max retries reached or not in failed state)');
        }

        $newSubmission = $submission->createRetry();

        return $this->submit(
            $submission->declaration,
            $submission->target,
            true // Always use AI for retries
        );
    }

    /**
     * Test connection to a target
     */
    public function testConnection(WebFormTarget $target): array
    {
        $credentials = $target->getPlaywrightCredentials();

        $result = $this->playwright->testConnection(
            $target->full_login_url,
            $credentials
        );

        if ($result['success']) {
            $target->markTested();
        }

        return $result;
    }

    /**
     * Build input for the Playwright script
     */
    protected function buildPlaywrightInput(
        WebFormTarget $target,
        array $mappedData,
        array $credentials
    ): array {
        return [
            'action' => 'submit',
            'baseUrl' => $target->base_url,
            'loginUrl' => $target->login_url,
            'credentials' => $credentials,
            'data' => $mappedData['fields'],
            'fieldMappings' => $mappedData['mappings'],
            'workflowSteps' => $target->workflow_steps ?? [],
            'headless' => true,
            'screenshotDir' => storage_path('app/playwright-screenshots'),
            'claudeApiKey' => config('services.claude.api_key') ?? env('CLAUDE_API_KEY'),
            'maxRetries' => 3,
        ];
    }

    /**
     * Build input for CAPS-specific Playwright script
     */
    protected function buildCapsPlaywrightInput(
        DeclarationForm $declaration,
        WebFormTarget $target,
        string $action = 'save'
    ): array {
        $capsData = $this->dataMapper->buildCapsSubmissionData($declaration, $target);
        
        return [
            'action' => $action,
            'loginUrl' => $capsData['loginUrl'],
            'credentials' => $capsData['credentials'],
            'headerData' => $capsData['headerData'],
            'items' => $capsData['items'],
            'headless' => true,
            'screenshotDir' => storage_path('app/playwright-screenshots'),
            'timeout' => 30000,
            'slowMo' => 50,
        ];
    }

    /**
     * Check if target is CAPS
     */
    protected function isCapsTarget(WebFormTarget $target): bool
    {
        return str_contains(strtolower($target->base_url), 'caps.gov.vg') ||
               str_contains(strtolower($target->name), 'caps');
    }

    /**
     * Execute Playwright and handle the result
     */
    protected function executePlaywright(array $input, WebFormSubmission $submission): array
    {
        // Write input to temp file
        $tempFile = storage_path('app/playwright-input-' . $submission->id . '.json');
        file_put_contents($tempFile, json_encode($input, JSON_PRETTY_PRINT));

        try {
            // Choose script based on AI mode
            $scriptPath = $this->playwright->isAIEnabled()
                ? base_path('playwright/ai-web-form-submitter.mjs')
                : base_path('playwright/web-form-submitter.mjs');

            // For now, use the generic dynamic submitter
            // In production, you'd have target-specific scripts
            $scriptPath = base_path('playwright/dynamic-web-submitter.mjs');

            // Check if dynamic script exists, fall back to AI script
            if (!file_exists($scriptPath)) {
                $scriptPath = base_path('playwright/ai-web-form-submitter.mjs');
            }

            $result = \Illuminate\Support\Facades\Process::timeout(180)
                ->run("node \"{$scriptPath}\" --input-file=\"{$tempFile}\"");

            $output = $result->output();
            $parsed = json_decode($output, true);

            if (!$parsed) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse Playwright output',
                    'raw_output' => $output,
                ];
            }

            return $parsed;

        } finally {
            // Clean up temp file
            @unlink($tempFile);
        }
    }

    /**
     * Execute CAPS-specific Playwright script
     */
    protected function executeCapsPlaywright(array $input, WebFormSubmission $submission): array
    {
        // Write input to temp file
        $tempFile = storage_path('app/playwright-caps-input-' . $submission->id . '.json');
        file_put_contents($tempFile, json_encode($input, JSON_PRETTY_PRINT));

        try {
            $scriptPath = base_path('playwright/caps-web-submitter.mjs');

            if (!file_exists($scriptPath)) {
                throw new \Exception('CAPS Playwright script not found');
            }

            $result = \Illuminate\Support\Facades\Process::timeout(300) // 5 minutes for complex forms
                ->run("node \"{$scriptPath}\" --input-file=\"{$tempFile}\"");

            $output = $result->output();
            $parsed = json_decode($output, true);

            if (!$parsed) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse CAPS Playwright output',
                    'raw_output' => $output,
                    'stderr' => $result->errorOutput(),
                ];
            }

            return $parsed;

        } finally {
            // Clean up temp file
            @unlink($tempFile);
        }
    }

    /**
     * Submit to CAPS specifically with AI-assisted mapping
     */
    public function submitToCaps(
        DeclarationForm $declaration,
        WebFormTarget $target,
        string $action = 'save',
        bool $useAI = true
    ): WebFormSubmission {
        // Create submission record
        $submission = WebFormSubmission::create([
            'web_form_target_id' => $target->id,
            'declaration_form_id' => $declaration->id,
            'user_id' => auth()->id(),
            'organization_id' => $declaration->organization_id,
            'status' => WebFormSubmission::STATUS_PENDING,
        ]);

        try {
            $submission->start();
            $submission->addLog('Starting CAPS submission' . ($useAI ? ' with AI assistance' : ''));

            // Build CAPS-specific input (raw data)
            $playwrightInput = $this->buildCapsPlaywrightInput($declaration, $target, $action);
            $submission->addLog('Prepared ' . count($playwrightInput['items']) . ' items for submission');

            // Apply AI-assisted mapping if enabled
            if ($useAI && $target->country_id) {
                $submission->addLog('Applying Claude AI mapping...');
                
                $this->aiMapper->setCountryId($target->country_id);
                $this->aiMapper->clearDecisions();
                
                // Map header data with AI
                $playwrightInput['headerData'] = $this->aiMapper->mapHeaderData($playwrightInput['headerData']);
                
                // Map items data with AI
                $playwrightInput['items'] = $this->aiMapper->mapItemsData($playwrightInput['items']);
                
                // Log AI decisions
                $aiDecisions = $this->aiMapper->getAiDecisions();
                if (!empty($aiDecisions)) {
                    $submission->addLog('AI made ' . count($aiDecisions) . ' mapping decisions');
                    foreach ($aiDecisions as $decision) {
                        $submission->addAiDecision(
                            "Field: {$decision['field']} - Input: {$decision['input_value']}",
                            "Mapped to: {$decision['matched_code']}",
                            $decision['reasoning'] ?? ''
                        );
                    }
                }
            }

            // Execute Playwright
            $submission->addLog('Executing CAPS Playwright automation');
            $result = $this->executeCapsPlaywright($playwrightInput, $submission);

            // Process result
            if ($result['success']) {
                $submission->markSubmitted(
                    $result['td_number'] ?? $result['reference_number'] ?? null,
                    $result['message'] ?? null
                );
                $submission->addLog('CAPS submission successful: TD ' . ($result['td_number'] ?? 'N/A'), 'success');

                // Update declaration
                if ($action === 'submit') {
                    $declaration->update([
                        'submission_status' => DeclarationForm::SUBMISSION_STATUS_SUBMITTED,
                        'submission_reference' => $result['td_number'],
                        'submitted_at' => now(),
                        'submitted_by_user_id' => auth()->id(),
                    ]);
                }
            } else {
                $submission->markFailed(
                    $result['error'] ?? 'Unknown CAPS error',
                    $result['errors'] ?? null
                );
                $submission->addLog('CAPS submission failed: ' . ($result['error'] ?? 'Unknown error'), 'error');
            }

            // Store screenshots
            if (!empty($result['screenshots'])) {
                foreach ($result['screenshots'] as $screenshot) {
                    $submission->addScreenshot($screenshot);
                }
            }

            // Handle validation warnings with AI interpretation
            if (!empty($result['warnings']) && $useAI) {
                $submission->addLog('Analyzing validation warnings with AI...');
                
                $interpretedErrors = $this->aiMapper->interpretValidationErrors($result['warnings']);
                
                foreach ($result['warnings'] as $warning) {
                    $submission->addLog('Warning: ' . $warning, 'warn');
                }
                
                // Store AI interpretations
                if (!empty($interpretedErrors)) {
                    foreach ($interpretedErrors as $interpreted) {
                        $submission->addAiDecision(
                            'Validation: ' . ($interpreted['error'] ?? 'Unknown'),
                            'Suggestion: ' . ($interpreted['suggestion'] ?? 'No suggestion'),
                            'Category: ' . ($interpreted['category'] ?? 'unknown') . 
                            ', Severity: ' . ($interpreted['severity'] ?? 'unknown')
                        );
                    }
                }
            } elseif (!empty($result['warnings'])) {
                foreach ($result['warnings'] as $warning) {
                    $submission->addLog('Warning: ' . $warning, 'warn');
                }
            }

        } catch (\Exception $e) {
            Log::error('CAPS submission failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $submission->markFailed($e->getMessage());
            $submission->addLog('Exception: ' . $e->getMessage(), 'error');
        }

        return $submission->fresh();
    }
}
