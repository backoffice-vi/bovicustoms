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
    protected CapsPreValidationService $capsPreValidation;

    public function __construct(
        PlaywrightService $playwright, 
        WebFormDataMapper $dataMapper,
        CapsAIMapper $aiMapper,
        CapsErrorRecoveryService $capsErrorRecovery,
        CapsPreValidationService $capsPreValidation
    ) {
        $this->playwright = $playwright;
        $this->dataMapper = $dataMapper;
        $this->aiMapper = $aiMapper;
        $this->capsErrorRecovery = $capsErrorRecovery;
        $this->capsPreValidation = $capsPreValidation;
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
     * Gather B/L and Invoice file attachments for the declaration.
     * Delegates to the shared DeclarationAttachmentGatherer.
     */
    protected function gatherAttachments(DeclarationForm $declaration): array
    {
        $attachments = app(\App\Services\Documents\DeclarationAttachmentGatherer::class)
            ->gatherForWebSubmission($declaration);

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
    protected function resolveNodePath(): string
    {
        $configured = config('services.node.path');
        if ($configured && file_exists($configured)) {
            return $configured;
        }

        $possiblePaths = PHP_OS_FAMILY === 'Windows'
            ? [
                'C:\\Program Files\\nodejs\\node.exe',
                'C:\\Program Files (x86)\\nodejs\\node.exe',
                getenv('LOCALAPPDATA') . '\\Programs\\nodejs\\node.exe',
            ]
            : ['/usr/bin/node', '/usr/local/bin/node'];

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return 'node';
    }

    /**
     * Build environment variables needed for Playwright/Chromium to run.
     */
    protected function getPlaywrightEnv(): array
    {
        $env = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $tempDir = sys_get_temp_dir();
            $env['TEMP'] = $tempDir;
            $env['TMP'] = $tempDir;
            $env['USERPROFILE'] = getenv('USERPROFILE') ?: 'C:\\Users\\Default';
            $env['LOCALAPPDATA'] = getenv('LOCALAPPDATA') ?: $env['USERPROFILE'] . '\\AppData\\Local';
            $env['APPDATA'] = getenv('APPDATA') ?: $env['USERPROFILE'] . '\\AppData\\Roaming';
            $env['HOME'] = $env['USERPROFILE'];
            $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';
            $env['SystemRoot'] = $systemRoot;
            $env['PATH'] = implode(';', array_filter([
                dirname($this->resolveNodePath()),
                $systemRoot . '\\system32',
                $systemRoot,
                getenv('PATH'),
            ]));
        }

        return $env;
    }

    protected function executePlaywright(array $input, WebFormSubmission $submission): array
    {
        $tempFile = storage_path('app/playwright-input-' . $submission->id . '.json');
        file_put_contents($tempFile, json_encode($input, JSON_PRETTY_PRINT));

        try {
            $scriptPath = $this->playwright->isAIEnabled()
                ? base_path('playwright/ai-web-form-submitter.mjs')
                : base_path('playwright/web-form-submitter.mjs');

            $scriptPath = base_path('playwright/dynamic-web-submitter.mjs');

            if (!file_exists($scriptPath)) {
                $scriptPath = base_path('playwright/ai-web-form-submitter.mjs');
            }

            $nodePath = $this->resolveNodePath();
            $env = $this->getPlaywrightEnv();

            $result = \Illuminate\Support\Facades\Process::timeout(180)
                ->env($env)
                ->run("\"{$nodePath}\" \"{$scriptPath}\" --input-file=\"{$tempFile}\"");

            $output = $result->output();
            $parsed = json_decode($output, true);

            if (!$parsed) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse Playwright output',
                    'raw_output' => $output,
                    'stderr' => $result->errorOutput(),
                ];
            }

            return $parsed;

        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Execute CAPS-specific Playwright script
     */
    protected function executeCapsPlaywright(array $input, WebFormSubmission $submission): array
    {
        $tempFile = storage_path('app/playwright-caps-input-' . $submission->id . '.json');
        $progressFile = storage_path('app/playwright-caps-progress-' . $submission->id . '.json');
        $input['progressFile'] = $progressFile;
        file_put_contents($tempFile, json_encode($input, JSON_PRETTY_PRINT));

        try {
            $scriptPath = base_path('playwright/caps-web-submitter.mjs');

            if (!file_exists($scriptPath)) {
                throw new \Exception('CAPS Playwright script not found');
            }

            $nodePath = $this->resolveNodePath();
            $env = $this->getPlaywrightEnv();

            $itemCount = count($input['items'] ?? []);
            $timeoutSeconds = max(300, 180 + ($itemCount * 30));
            $input['slowMo'] = 0;
            file_put_contents($tempFile, json_encode($input, JSON_PRETTY_PRINT));
            $submission->addLog("Running: {$nodePath} caps-web-submitter.mjs ({$itemCount} items, timeout: {$timeoutSeconds}s)");

            try {
                $result = \Illuminate\Support\Facades\Process::timeout($timeoutSeconds)
                    ->env($env)
                    ->run("\"{$nodePath}\" \"{$scriptPath}\" --input-file=\"{$tempFile}\"");
            } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException|\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
                $submission->addLog("Playwright timed out after {$timeoutSeconds}s", 'error');
                $progress = $this->readCapsProgress($progressFile);
                $this->captureDraftTdNumber($submission, $progress);

                return [
                    'success' => false,
                    'error' => "Playwright timed out after {$timeoutSeconds} seconds ({$itemCount} items)",
                    'raw_output' => '',
                    'stderr' => 'Process timed out',
                    'exit_code' => -1,
                    'td_number' => $progress['td_number'] ?? null,
                    'reference_number' => $progress['reference_number'] ?? ($progress['td_number'] ?? null),
                    'progress' => $progress,
                ];
            }

            $output = $result->output();
            $stderr = $result->errorOutput();
            $exitCode = $result->exitCode();

            $submission->addLog("Playwright exit code: {$exitCode}");

            if ($stderr) {
                $stderrSnippet = substr($stderr, -1000);
                $submission->addLog("Playwright stderr (last 1000 chars): {$stderrSnippet}", 'debug');
            }

            $parsed = json_decode($output, true);
            $progress = $this->readCapsProgress($progressFile);

            if (!$parsed) {
                $outputSnippet = $output ? substr($output, 0, 500) : '(empty)';
                $submission->addLog("Failed to parse output. Raw stdout: {$outputSnippet}", 'error');
                $this->captureDraftTdNumber($submission, $progress);

                Log::warning('CAPS Playwright parse failure', [
                    'exit_code' => $exitCode,
                    'stdout_length' => strlen($output),
                    'stdout_preview' => substr($output, 0, 500),
                    'stderr_preview' => substr($stderr, -500),
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to parse CAPS Playwright output',
                    'raw_output' => $output,
                    'stderr' => $stderr,
                    'exit_code' => $exitCode,
                    'td_number' => $progress['td_number'] ?? null,
                    'reference_number' => $progress['reference_number'] ?? ($progress['td_number'] ?? null),
                    'progress' => $progress,
                ];
            }

            if (!empty($progress)) {
                $parsed['progress'] = $progress;
                $parsed['td_number'] = $parsed['td_number'] ?? ($progress['td_number'] ?? null);
                $parsed['reference_number'] = $parsed['reference_number'] ?? ($progress['reference_number'] ?? ($progress['td_number'] ?? null));
                $this->captureDraftTdNumber($submission, $parsed);
            }

            return $parsed;

        } finally {
            @unlink($tempFile);
        }
    }

    protected function readCapsProgress(string $progressFile): array
    {
        if (!file_exists($progressFile)) {
            return [];
        }

        $progress = json_decode((string) file_get_contents($progressFile), true);

        return is_array($progress) ? $progress : [];
    }

    protected function captureDraftTdNumber(WebFormSubmission $submission, array $resultOrProgress): void
    {
        $tdNumber = $resultOrProgress['td_number'] ?? $resultOrProgress['reference_number'] ?? null;

        if (empty($tdNumber)) {
            return;
        }

        $requestData = $submission->request_data ?? [];
        if (($requestData['draft_td_number'] ?? null) === $tdNumber && $submission->external_reference === $tdNumber) {
            return;
        }

        $requestData['draft_td_number'] = $tdNumber;
        $requestData['draft_td_captured_at'] = now()->toIso8601String();

        if (!empty($resultOrProgress['event'])) {
            $requestData['draft_td_event'] = $resultOrProgress['event'];
        }

        $submission->update([
            'request_data' => $requestData,
            'external_reference' => $submission->external_reference ?: $tdNumber,
        ]);
        $submission->addLog("Captured CAPS draft TD number: {$tdNumber}", 'success');
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
        $itemCount = $declaration->declarationItems()->count();
        $maxTime = max(900, 300 + ($itemCount * 20));
        set_time_limit($maxTime);
        ini_set('max_execution_time', (string) $maxTime);

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

            $playwrightInput['country_id'] = $target->country_id;
            $validation = $this->capsPreValidation->validateWebPayload($playwrightInput, $target->country_id);
            $submission->update([
                'request_data' => [
                    'action' => $action,
                    'item_count' => count($playwrightInput['items'] ?? []),
                    'caps_pre_validation' => $validation,
                ],
                'mapped_data' => $this->capsPreValidation->redactPayload($playwrightInput),
            ]);

            if (!$validation['valid']) {
                $submission->addLog('CAPS pre-validation failed; Playwright automation was not started.', 'error');
                foreach (array_slice($validation['errors'], 0, 10) as $error) {
                    $submission->addLog('Pre-validation error: ' . $error, 'error');
                }
                $submission->markFailed('CAPS pre-validation failed. Fix the payload errors before submitting.', $validation);
                return $submission;
            }

            if (!empty($validation['warnings'])) {
                foreach (array_slice($validation['warnings'], 0, 10) as $warning) {
                    $submission->addLog('Pre-validation warning: ' . $warning, 'warn');
                }
            }

            // --- retry loop ---
            $result = null;
            for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                if ($attempt > 0) {
                    $submission->addLog("--- Retry attempt #{$attempt} ---", 'info');
                }

                $submission->addLog('Executing CAPS Playwright automation (attempt ' . ($attempt + 1) . ')');
                $result = $this->executeCapsPlaywright($playwrightInput, $submission);
                $draftTdNumber = $result['td_number'] ?? $result['reference_number'] ?? null;
                if (!empty($draftTdNumber)) {
                    $playwrightInput['td_number'] = $draftTdNumber;
                    $this->captureDraftTdNumber($submission, $result);
                }

                $this->storeScreenshots($result, $submission);
                $this->storeCapsResultReport($submission, $result);

                $succeeded = !empty($result['success']) && ($result['validation_passed'] ?? true) !== false;

                if ($succeeded) {
                    break;
                }

                // Treat validation_passed === false as failure even if success === true
                if (!empty($result['success']) && ($result['validation_passed'] ?? true) === false) {
                    $submission->addLog('CAPS validation failed despite successful save', 'warn');
                }

                if ($attempt < $maxRetries) {
                    $submission->addLog('Analyzing errors for auto-recovery (pattern + AI)...');
                    $recovery = $this->capsErrorRecovery->analyze($result, $playwrightInput);

                    if ($recovery['can_retry']) {
                        if (!empty($recovery['fixes_applied'])) {
                            $playwrightInput = $recovery['fixed_input'];
                            if (!empty($draftTdNumber)) {
                                $playwrightInput['td_number'] = $draftTdNumber;
                            }
                            foreach ($recovery['fixes_applied'] as $fix) {
                                $submission->addLog("Auto-fix: {$fix}", 'info');
                            }
                            $submission->addAiDecision(
                                'Error Recovery (attempt ' . ($attempt + 1) . ')',
                                implode('; ', $recovery['fixes_applied']),
                                $recovery['diagnosis'] ?? ''
                            );
                        } else {
                            $submission->addLog('Transient error detected — retrying without changes', 'info');
                            $submission->addAiDecision(
                                'Transient Retry (attempt ' . ($attempt + 1) . ')',
                                'Retrying due to transient error (parse failure, timeout, etc.)',
                                $recovery['diagnosis'] ?? ''
                            );
                        }
                        continue;
                    }

                    // Cannot auto-fix and not transient — store diagnosis and stop
                    $this->storeDiagnosis($submission, $recovery);
                    $submission->addLog('No auto-fix available and error is not transient — stopping retries', 'warn');
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
                if (($result['validation_passed'] ?? true) === false && empty($result['error'])) {
                    $errorMsg = 'CAPS validation failed';
                }
                $errors = !empty($result['validation_errors'])
                    ? $result['validation_errors']
                    : ($result['errors'] ?? null);
                $submission->markFailed($errorMsg, $errors);
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

    /**
     * Store the latest CAPS response in a compact report for the result page.
     */
    protected function storeCapsResultReport(WebFormSubmission $submission, array $result): void
    {
        $validationErrors = $this->normalizeCapsMessages($result['validation_errors'] ?? []);
        $errors = $this->normalizeCapsMessages($result['errors'] ?? []);
        $warnings = $this->normalizeCapsMessages($result['warnings'] ?? []);

        foreach (array_slice($validationErrors, 0, 25) as $error) {
            $submission->addLog('CAPS validation error: ' . $error, 'error');
        }

        $submission->update([
            'response_data' => array_merge($submission->response_data ?? [], [
                'caps_result' => [
                    'success' => (bool) ($result['success'] ?? false),
                    'validation_passed' => $result['validation_passed'] ?? null,
                    'message' => $result['message'] ?? null,
                    'error' => $result['error'] ?? null,
                    'td_number' => $result['td_number'] ?? null,
                    'reference_number' => $result['reference_number'] ?? null,
                    'exit_code' => $result['exit_code'] ?? null,
                ],
                'caps_validation_errors' => $validationErrors,
                'caps_errors' => $errors,
                'caps_warnings' => $warnings,
            ]),
        ]);
    }

    protected function normalizeCapsMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $message = $message['message'] ?? json_encode($message);
            }

            $message = trim((string) $message);
            if ($message !== '') {
                $normalized[] = $message;
            }
        }

        return array_values(array_unique($normalized));
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
