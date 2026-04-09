<?php

namespace App\Services;

use App\Models\CapsImport;
use Illuminate\Support\Facades\Log;

class CapsImportErrorRecoveryService
{
    protected ClaudeJsonClient $claude;

    protected const ERROR_PATTERNS = [
        'login_failure' => '/login\s*fail|invalid\s*credentials|authentication\s*fail|incorrect\s*password|incorrect\s*email/i',
        'session_expired' => '/session\s*(?:expired|timeout)|logged\s*out|please\s*log\s*in/i',
        'chrome_not_found' => '/chrome.*not\s*found|chromium.*not\s*found|no\s*(?:chrome|browser)|executable.*not\s*exist/i',
        'td_not_found' => '/td\s*not\s*found|declaration\s*not\s*found|no\s*(?:td|declaration)\s*(?:with|matching)|does\s*not\s*exist/i',
        'access_denied' => '/access\s*denied|permission|forbidden|not\s*authorized|403/i',
        'caps_maintenance' => '/maintenance|service\s*unavailable|temporarily\s*unavailable|503|under\s*construction/i',
        'data_missing' => '/no\s*data\.json|data\.json\s*not\s*found|download\s*completed\s*but\s*no\s*data/i',
        'import_error' => '/import\s*fail|database.*error|integrity\s*constraint|duplicate\s*entry|SQLSTATE/i',
        'extraction_error' => '/extract(?:ion)?\s*fail|no\s*items\s*extracted|pdf.*(?:error|fail|corrupt)/i',
        'scraping_error' => '/selector.*not\s*found|element.*not\s*found|could\s*not\s*(?:find|locate)|waiting\s*for\s*selector/i',
        'attachment_error' => '/download\s*fail|attachment.*fail|could\s*not\s*download|popup.*fail|no\s*attachments/i',
        'parsing_error' => '/json.*parse|invalid\s*json|unexpected\s*token|cannot\s*read\s*propert/i',
        'network_error' => '/ECONNREFUSED|ETIMEDOUT|ENOTFOUND|connection\s*refused|ERR_CONNECTION|fetch\s*failed/i',
        'timeout' => '/timeout|timed?\s*out|exceeded.*time|navigation\s*timeout|waiting.*exceeded/i',
    ];

    protected const RETRIABLE_CATEGORIES = [
        'network_error', 'timeout', 'session_expired', 'caps_maintenance', 'scraping_error',
    ];

    protected const NOT_RETRIABLE_CATEGORIES = [
        'login_failure', 'chrome_not_found', 'access_denied', 'td_not_found',
    ];

    public function __construct(ClaudeJsonClient $claude)
    {
        $this->claude = $claude;
    }

    /**
     * Analyze a failure from the CAPS download/import pipeline.
     *
     * @param string $errorMessage  Raw error text (stderr, exception message, etc.)
     * @param string $phase         Which phase failed: 'download', 'import', 'invoice_processing'
     * @param array  $context       Extra context (td_number, retry_count, process output, etc.)
     */
    public function analyze(string $errorMessage, string $phase = 'download', array $context = []): array
    {
        $classified = $this->classifyError($errorMessage);
        $canRetry = $this->isRetriable($classified);
        $suggestions = $this->getSuggestions($classified, $phase);

        $aiAnalysis = $this->askClaudeForDiagnosis($errorMessage, $phase, $classified, $context);

        return [
            'can_retry' => $canRetry,
            'error_category' => $classified['category'],
            'error_categories' => [$classified],
            'diagnosis' => $aiAnalysis['diagnosis'] ?? $this->fallbackDiagnosis($classified, $phase),
            'recommendations' => $aiAnalysis['recommendations'] ?? $suggestions,
            'severity' => $aiAnalysis['severity'] ?? $classified['severity'],
            'fixes_applied' => [],
        ];
    }

    /**
     * Analyze multiple errors (e.g. from invoice processing with per-file errors).
     */
    public function analyzeMultiple(array $errors, string $phase = 'invoice_processing', array $context = []): array
    {
        $allClassified = [];
        $canRetry = false;
        $allSuggestions = [];

        foreach ($errors as $error) {
            $classified = $this->classifyError($error);
            $allClassified[] = $classified;
            if ($this->isRetriable($classified)) {
                $canRetry = true;
            }
            $allSuggestions = array_merge($allSuggestions, $this->getSuggestions($classified, $phase));
        }

        $combinedError = implode("\n", $errors);
        $aiAnalysis = $this->askClaudeForDiagnosis($combinedError, $phase, $allClassified[0] ?? [], $context);

        return [
            'can_retry' => $canRetry,
            'error_categories' => $allClassified,
            'diagnosis' => $aiAnalysis['diagnosis'] ?? 'Multiple errors occurred during ' . $phase,
            'recommendations' => $aiAnalysis['recommendations'] ?? array_unique($allSuggestions),
            'severity' => $aiAnalysis['severity'] ?? 'recoverable',
            'fixes_applied' => [],
        ];
    }

    protected function classifyError(string $error): array
    {
        $category = 'unknown';
        $severity = 'recoverable';

        foreach (self::ERROR_PATTERNS as $cat => $pattern) {
            if (preg_match($pattern, $error)) {
                $category = $cat;
                break;
            }
        }

        if (in_array($category, self::NOT_RETRIABLE_CATEGORIES)) {
            $severity = 'critical';
        } elseif (in_array($category, self::RETRIABLE_CATEGORIES)) {
            $severity = 'recoverable';
        } elseif ($category === 'unknown') {
            $severity = 'unknown';
        }

        return [
            'error' => $error,
            'category' => $category,
            'severity' => $severity,
            'retriable' => in_array($category, self::RETRIABLE_CATEGORIES),
        ];
    }

    protected function isRetriable(array $classified): bool
    {
        return $classified['retriable'] ?? false;
    }

    protected function getSuggestions(array $classified, string $phase): array
    {
        return match ($classified['category']) {
            'login_failure' => [
                'Verify your CAPS username and password are correct.',
                'Check if your CAPS account has been locked or deactivated.',
                'Try logging into caps.gov.vg manually to confirm credentials work.',
            ],
            'session_expired' => [
                'This is a temporary issue — the session expired during processing.',
                'Retry the download; a new session will be created automatically.',
            ],
            'network_error' => [
                'Check your internet connection.',
                'The CAPS server may be temporarily unreachable — retry in a few minutes.',
                'If this persists, the CAPS portal may be down for maintenance.',
            ],
            'timeout' => [
                'The CAPS portal took too long to respond.',
                'Retry the download — the portal may be under heavy load.',
                'Consider increasing the download timeout in settings.',
            ],
            'chrome_not_found' => [
                'Google Chrome or Chromium is not installed or not found on this server.',
                'Install Chrome/Chromium or set the CHROME_PATH environment variable.',
            ],
            'td_not_found' => [
                'This Trade Declaration may have been removed from CAPS.',
                'Verify the TD number is correct.',
                'The TD may not be accessible with your current CAPS account.',
            ],
            'access_denied' => [
                'Your CAPS account may not have permission to view this declaration.',
                'Contact BVI Customs to verify your account access level.',
            ],
            'caps_maintenance' => [
                'The CAPS portal is currently under maintenance.',
                'Retry later when the portal is back online.',
            ],
            'scraping_error' => [
                'The CAPS portal page structure may have changed.',
                'Retry the download — the page may not have loaded fully.',
                'If this persists, the automation script may need updating.',
            ],
            'attachment_error' => [
                'One or more attachments could not be downloaded.',
                'The attachments may not be available for this TD.',
                'Retry the download to attempt fetching attachments again.',
            ],
            'parsing_error' => [
                'The data returned from CAPS could not be parsed.',
                'This may indicate the CAPS portal returned an unexpected page.',
                'Retry the download — it may succeed on a fresh attempt.',
            ],
            'data_missing' => [
                'The download completed but the scraped data file was not created.',
                'The TD page may have loaded but the content could not be extracted.',
                'Retry the download to attempt a fresh scrape.',
            ],
            'import_error' => [
                'A database error occurred while importing the TD data.',
                'The data may already exist under a different record.',
                'Check the TD data for missing or malformed fields.',
            ],
            'extraction_error' => [
                'The invoice PDF could not be processed or had no extractable items.',
                'The PDF may be scanned/image-based rather than text-based.',
                'Try processing a different attachment or enter items manually.',
            ],
            default => [
                'An unexpected error occurred during ' . $phase . '.',
                'Retry the operation or check the application logs for details.',
            ],
        };
    }

    protected function fallbackDiagnosis(array $classified, string $phase): string
    {
        $categoryLabel = str_replace('_', ' ', $classified['category']);
        return "A {$categoryLabel} error occurred during the {$phase} phase. " .
            ($classified['retriable'] ? 'This type of error is typically temporary and may resolve on retry.' : 'This error may require manual intervention.');
    }

    protected function askClaudeForDiagnosis(string $errorMessage, string $phase, array $classified, array $context): array
    {
        $tdNumber = $context['td_number'] ?? 'unknown';
        $retryCount = $context['retry_count'] ?? 0;
        $processOutput = $context['process_output'] ?? '';
        $category = $classified['category'] ?? 'unknown';

        $outputSnippet = mb_substr($processOutput, 0, 1500);

        $prompt = <<<PROMPT
You are a CAPS (BVI Customs Automated Processing System) portal automation expert.

A Playwright browser automation script that downloads Trade Declarations from the CAPS web portal has failed.

Phase: {$phase}
TD Number: {$tdNumber}
Retry count: {$retryCount}
Error category: {$category}

Error message:
{$errorMessage}

Process output (last 1500 chars):
{$outputSnippet}

Known context about CAPS portal automation:
- The script logs into caps.gov.vg, navigates to the TD list, and scrapes each declaration
- It downloads attachments (invoices, B/L documents) via HTTP with session cookies
- It screenshots the declaration page and extracts structured data to data.json
- Common issues: session timeouts, CAPTCHA challenges, element selectors changing, slow page loads
- The portal sometimes requires clicking through multiple pages and popups
- Attachments are downloaded via popup windows or direct HTTP requests

Provide a clear diagnosis and actionable recommendations for the user.

Return JSON only:
{
  "diagnosis": "Clear 1-2 sentence explanation of what went wrong and why",
  "recommendations": ["Actionable step 1", "Step 2", "Step 3"],
  "severity": "critical" | "recoverable" | "minor"
}
PROMPT;

        try {
            $result = $this->claude->promptForJson($prompt, 30, 500);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            Log::warning('CapsImportErrorRecovery: Claude diagnosis failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
