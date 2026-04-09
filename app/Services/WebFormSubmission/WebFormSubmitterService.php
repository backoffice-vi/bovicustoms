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
    protected CapsErrorRecoveryService $capsErrorRecovery;

    public function __construct(
        PlaywrightService $playwright, 
        WebFormDataMapper $dataMapper,
        CapsAIMapper $aiMapper,
        CapsErrorRecoveryService $capsErrorRecovery
    ) {
        $this->playwright = $playwright;
        $this->dataMapper = $dataMapper;
        $this->aiMapper = $aiMapper;
        $this->capsErrorRecovery = $capsErrorRecovery;
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
     * Retry a failed submission, routing CAPS targets through submitToCaps().
     */
    public function retry(WebFormSubmission $submission): WebFormSubmission
    {
        if (!$submission->can_retry) {
            throw new \Exception('This submission cannot be retried (max retries reached or not in failed state)');
        }

        $submission->createRetry();

        $declaration = $submission->declaration;
        $target = $submission->target;

        if ($this->isCapsTarget($target)) {
            $lastAction = $submission->request_data['action'] ?? 'save';
            return $this->submitToCaps($declaration, $target, $lastAction, true);
        }

        return $this->submit($declaration, $target, true);
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
            'attachments' => $this->gatherAttachments($declaration),
            'headless' => true,
            'screenshotDir' => storage_path('app/playwright-screenshots'),
            'timeout' => 30000,
            'slowMo' => 50,
        ];
    }

    /**
     * Gather B/L and Invoice file attachments for the declaration
     */
    protected function gatherAttachments(DeclarationForm $declaration): array
    {
        $attachments = [];

        $declaration->load(['shipment.shippingDocuments', 'invoice']);

        // B/L or AWB from shipping documents
        if ($declaration->shipment) {
            $transportDoc = $declaration->shipment->shippingDocuments
                ->filter(fn($doc) => $doc->isPrimaryTransportDocument() && $doc->file_path)
                ->first();

            if ($transportDoc) {
                $absPath = storage_path('app/' . $transportDoc->file_path);
                if (file_exists($absPath)) {
                    $attachments[] = [
                        'label' => $transportDoc->document_type_label . ' - ' . ($transportDoc->original_filename ?? 'B/L'),
                        'filePath' => $absPath,
                        'type' => $transportDoc->document_type,
                    ];
                    Log::info('CAPS attachment: B/L found', ['path' => $absPath]);
                }
            }
        }

        // Invoice PDF
        $allInvoices = $declaration->getAllInvoices();
        foreach ($allInvoices as $invoice) {
            if (!empty($invoice->source_file_path)) {
                $absPath = storage_path('app/' . $invoice->source_file_path);
                if (file_exists($absPath)) {
                    $attachments[] = [
                        'label' => 'Invoice #' . ($invoice->invoice_number ?? $invoice->id),
                        'filePath' => $absPath,
                        'type' => 'invoice',
                    ];
                    Log::info('CAPS attachment: Invoice found', ['path' => $absPath]);
                }
            }
        }

        if (empty($attachments)) {
            Log::info('CAPS submission: No attachment files found for declaration ' . $declaration->id);
        }

        return $attachments;
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
     * Submit to CAPS with AI-assisted mapping and automatic error recovery.
     *
     * The method runs a retry loop (up to $maxRetries additional attempts).
     * After each failure, CapsErrorRecoveryService analyses the errors,
     * applies auto-fixes to the input data, and retries.
     */
    public function submitToCaps(
        DeclarationForm $declaration,
        WebFormTarget $target,
        string $action = 'save',
        bool $useAI = true,
        int $maxRetries = 2
    ): WebFormSubmission {
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

            $playwrightInput = $this->buildCapsPlaywrightInput($declaration, $target, $action);
            $playwrightInput['country_id'] = $target->country_id;
            $submission->addLog('Prepared ' . count($playwrightInput['items']) . ' items for submission');

            if ($useAI && $target->country_id) {
                $playwrightInput = $this->applyCapsAIMapping($playwrightInput, $target, $submission);
            }

            // --- retry loop ---
            $result = null;
            for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                if ($attempt > 0) {
                    $submission->addLog("--- Retry attempt #{$attempt} ---", 'info');
                }

                $submission->addLog('Executing CAPS Playwright automation (attempt ' . ($attempt + 1) . ')');
                $result = $this->executeCapsPlaywright($playwrightInput, $submission);

                $this->storeScreenshots($result, $submission);

                $succeeded = !empty($result['success']) && ($result['validation_passed'] ?? true) !== false;

                if ($succeeded) {
                    break;
                }

                // Treat validation_passed === false as failure even if success === true
                if (!empty($result['success']) && ($result['validation_passed'] ?? true) === false) {
                    $submission->addLog('CAPS validation failed despite successful save', 'warn');
                }

                if ($attempt < $maxRetries) {
                    $submission->addLog('Analyzing errors for auto-recovery...');
                    $recovery = $this->capsErrorRecovery->analyze($result, $playwrightInput);

                    if ($recovery['can_retry'] && !empty($recovery['fixes_applied'])) {
                        $playwrightInput = $recovery['fixed_input'];
                        foreach ($recovery['fixes_applied'] as $fix) {
                            $submission->addLog("Auto-fix: {$fix}", 'info');
                        }
                        $submission->addAiDecision(
                            'Error Recovery (attempt ' . ($attempt + 1) . ')',
                            implode('; ', $recovery['fixes_applied']),
                            $recovery['diagnosis'] ?? ''
                        );
                        continue;
                    }

                    // Cannot auto-fix — store diagnosis and stop retrying
                    $this->storeDiagnosis($submission, $recovery);
                    $submission->addLog('No auto-fix available — stopping retries', 'warn');
                    break;
                }

                // Final attempt exhausted
                $recovery = $this->capsErrorRecovery->analyze($result, $playwrightInput);
                $this->storeDiagnosis($submission, $recovery);
            }

            // --- process final result ---
            $succeeded = !empty($result['success']) && ($result['validation_passed'] ?? true) !== false;

            if ($succeeded) {
                $tdNumber = $result['td_number'] ?? $result['reference_number'] ?? null;
                $submission->markSubmitted($tdNumber, $result['message'] ?? null);
                $submission->addLog('CAPS submission successful: TD ' . ($tdNumber ?? 'N/A'), 'success');

                if ($action === 'submit') {
                    $declaration->update([
                        'submission_status' => DeclarationForm::SUBMISSION_STATUS_SUBMITTED,
                        'submission_reference' => $tdNumber,
                        'submitted_at' => now(),
                        'submitted_by_user_id' => auth()->id(),
                    ]);
                }
            } else {
                $errorMsg = $result['error'] ?? 'Unknown CAPS error';
                if (($result['validation_passed'] ?? true) === false) {
                    $errorMsg = 'CAPS validation failed: ' . $errorMsg;
                }
                $submission->markFailed($errorMsg, $result['errors'] ?? null);
                $submission->addLog('CAPS submission failed: ' . $errorMsg, 'error');
            }

            // Log warnings via AI if present
            if (!empty($result['warnings'])) {
                $this->logCapsWarnings($result['warnings'], $useAI, $submission);
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

    /**
     * Apply AI-assisted field mapping for CAPS.
     */
    protected function applyCapsAIMapping(array $playwrightInput, WebFormTarget $target, WebFormSubmission $submission): array
    {
        $submission->addLog('Applying Claude AI mapping...');

        $this->aiMapper->setCountryId($target->country_id);
        $this->aiMapper->clearDecisions();

        $playwrightInput['headerData'] = $this->aiMapper->mapHeaderData($playwrightInput['headerData']);
        $playwrightInput['items'] = $this->aiMapper->mapItemsData($playwrightInput['items']);

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

        return $playwrightInput;
    }

    /**
     * Store AI diagnosis and recommendations on the submission.
     */
    protected function storeDiagnosis(WebFormSubmission $submission, array $recovery): void
    {
        $submission->addAiDecision(
            'Error Diagnosis',
            $recovery['diagnosis'] ?? 'Unknown',
            !empty($recovery['recommendations']) ? implode(' | ', $recovery['recommendations']) : ''
        );

        $submission->update([
            'response_data' => array_merge($submission->response_data ?? [], [
                'ai_diagnosis' => $recovery['diagnosis'] ?? null,
                'ai_recommendations' => $recovery['recommendations'] ?? [],
                'error_categories' => $recovery['error_categories'] ?? [],
                'auto_fixes_applied' => $recovery['fixes_applied'] ?? [],
            ]),
        ]);
    }

    protected function storeScreenshots(array $result, WebFormSubmission $submission): void
    {
        foreach ($result['screenshots'] ?? [] as $screenshot) {
            $submission->addScreenshot($screenshot);
        }
    }

    protected function logCapsWarnings(array $warnings, bool $useAI, WebFormSubmission $submission): void
    {
        foreach ($warnings as $warning) {
            $submission->addLog('Warning: ' . $warning, 'warn');
        }

        if ($useAI) {
            $submission->addLog('Analyzing validation warnings with AI...');
            $interpretedErrors = $this->aiMapper->interpretValidationErrors($warnings);
            foreach ($interpretedErrors as $interpreted) {
                $submission->addAiDecision(
                    'Validation: ' . ($interpreted['error'] ?? 'Unknown'),
                    'Suggestion: ' . ($interpreted['suggestion'] ?? 'No suggestion'),
                    'Category: ' . ($interpreted['category'] ?? 'unknown') .
                    ', Severity: ' . ($interpreted['severity'] ?? 'unknown')
                );
            }
        }
    }
}
