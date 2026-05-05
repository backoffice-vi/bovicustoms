<?php

namespace App\Services\WebFormSubmission;

use App\Models\CountryReferenceData;
use App\Services\ClaudeJsonClient;
use Illuminate\Support\Facades\Log;

class CapsErrorRecoveryService
{
    protected ClaudeJsonClient $claude;
    protected WebFormDataMapper $dataMapper;

    protected const AUTO_FIXABLE_PATTERNS = [
        'tariff_not_known' => '/TARIFF\s*(?:NO\.?)?\s*NOT\s*KNOWN/i',
        'payment_not_known' => '/PAYMENT\s*METHOD\s*NOT\s*KNOWN/i',
        'field_not_complete' => '/FIELD\s*NOT\s*COMPLETE/i',
        'supplier_not_found' => '/SUPPLIER\s*ID\s*NOT\s*FOUND/i',
        'trader_not_active' => '/TRADER\s*NOT\s*ACTIVE/i',
        'quantity_units_not_known' => '/QUANTITY\s*UNITS\s*NOT\s*KNOWN/i',
    ];

    protected const NOT_FIXABLE_PATTERNS = [
        'login_failure' => '/login\s*fail|invalid\s*credentials|authentication/i',
    ];

    protected const TRANSIENT_PATTERNS = [
        'network_error' => '/ECONNREFUSED|ETIMEDOUT|network|connection\s*refused/i',
        'parse_failure' => '/Failed to parse.*output|empty.*output|timeout|timed?\s*out/i',
        'unexpected_page' => '/unexpected.*page|navigation.*failed|page.*crashed/i',
    ];

    public function __construct(ClaudeJsonClient $claude, WebFormDataMapper $dataMapper)
    {
        $this->claude = $claude;
        $this->dataMapper = $dataMapper;
    }

    /**
     * Analyze a CAPS result and determine if we can auto-fix and retry.
     *
     * Strategy:
     * 1. Pattern-based fixes (fast, deterministic)
     * 2. If no pattern fixes, ask Claude to propose actual field changes
     * 3. Transient errors (parse failures, timeouts) always get a retry
     */
    public function analyze(array $result, array $inputData): array
    {
        $errors = $this->collectErrors($result);

        if (empty($errors)) {
            return $this->noErrorResult($inputData);
        }

        $rawContext = trim(($result['raw_output'] ?? '') . "\n" . ($result['stderr'] ?? ''));

        $classified = $this->classifyErrors($errors);
        $fixesApplied = [];
        $fixedInput = $inputData;

        // Phase 1: Pattern-based auto-fixes
        foreach ($classified as $entry) {
            $fix = $this->tryAutoFix($entry, $fixedInput);
            if ($fix) {
                $fixedInput = $fix['input'];
                $fixesApplied[] = $fix['description'];
            }
        }

        // Phase 2: If pattern fixes didn't resolve everything, ask AI for fixes
        $hasUnfixedErrors = count($fixesApplied) < count(array_filter($classified, fn($e) => $e['category'] !== 'login_failure'));
        $aiAnalysis = [];

        if ($hasUnfixedErrors) {
            $aiAnalysis = $this->askClaudeForFixes($errors, $fixedInput, $fixesApplied, $rawContext);

            // Apply AI-proposed field changes
            if (!empty($aiAnalysis['fixes'])) {
                $aiFixResult = $this->applyAiFixes($aiAnalysis['fixes'], $fixedInput);
                $fixedInput = $aiFixResult['input'];
                foreach ($aiFixResult['applied'] as $desc) {
                    $fixesApplied[] = "(AI) {$desc}";
                }
            }
        } else {
            $aiAnalysis = $this->askClaudeForDiagnosisOnly($errors, $fixedInput, $fixesApplied);
        }

        $isTransient = $this->isTransientError($classified);
        $canRetry = !empty($fixesApplied) || $isTransient;

        return [
            'can_retry' => $canRetry,
            'fixes_applied' => $fixesApplied,
            'fixed_input' => $fixedInput,
            'diagnosis' => $aiAnalysis['diagnosis'] ?? implode('; ', $errors),
            'recommendations' => $aiAnalysis['recommendations'] ?? [],
            'error_categories' => $classified,
        ];
    }

    protected function collectErrors(array $result): array
    {
        $errors = [];

        if (!empty($result['error'])) {
            $errors[] = $result['error'];
        }
        foreach ($result['errors'] ?? [] as $e) {
            $errors[] = is_string($e) ? $e : ($e['message'] ?? json_encode($e));
        }
        foreach ($result['warnings'] ?? [] as $w) {
            $errors[] = is_string($w) ? $w : ($w['message'] ?? json_encode($w));
        }
        foreach ($result['validation_errors'] ?? [] as $ve) {
            $errors[] = is_string($ve) ? $ve : ($ve['message'] ?? json_encode($ve));
        }

        return array_unique($errors);
    }

    protected function classifyErrors(array $errors): array
    {
        $classified = [];

        foreach ($errors as $error) {
            $category = 'unknown';
            $autoFixable = false;

            foreach (self::AUTO_FIXABLE_PATTERNS as $cat => $pattern) {
                if (preg_match($pattern, $error)) {
                    $category = $cat;
                    $autoFixable = true;
                    break;
                }
            }

            if (!$autoFixable) {
                foreach (self::NOT_FIXABLE_PATTERNS as $cat => $pattern) {
                    if (preg_match($pattern, $error)) {
                        $category = $cat;
                        break;
                    }
                }
            }

            if ($category === 'unknown') {
                foreach (self::TRANSIENT_PATTERNS as $cat => $pattern) {
                    if (preg_match($pattern, $error)) {
                        $category = $cat;
                        break;
                    }
                }
            }

            $recNumber = null;
            $boxNumber = null;
            if (preg_match('/Rec\s*(\d+)/i', $error, $m)) {
                $recNumber = (int) $m[1];
            }
            if (preg_match('/Box\s*([\da-z]+)/i', $error, $m)) {
                $boxNumber = $m[1];
            }

            $classified[] = [
                'error' => $error,
                'category' => $category,
                'auto_fixable' => $autoFixable,
                'record' => $recNumber,
                'box' => $boxNumber,
            ];
        }

        return $classified;
    }

    protected function tryAutoFix(array $entry, array $input): ?array
    {
        if (!$entry['auto_fixable']) {
            return null;
        }

        return match ($entry['category']) {
            'tariff_not_known' => $this->fixTariffCode($entry, $input),
            'payment_not_known' => $this->fixPaymentMethod($entry, $input),
            'field_not_complete' => $this->fixMissingField($entry, $input),
            'supplier_not_found' => $this->fixSupplierIdClear($entry, $input),
            'trader_not_active' => $this->fixSupplierIdClear($entry, $input),
            'quantity_units_not_known' => $this->fixUnits($entry, $input),
            default => null,
        };
    }

    // ==========================================
    // AI-driven fix proposal
    // ==========================================

    /**
     * Ask Claude to diagnose AND propose concrete field changes.
     */
    protected function askClaudeForFixes(array $errors, array $inputData, array $existingFixes, string $rawContext = ''): array
    {
        $errorsText = implode("\n- ", $errors);
        $fixesText = empty($existingFixes) ? 'None.' : implode("\n- ", $existingFixes);

        $headerJson = json_encode(
            collect($inputData['headerData'] ?? [])->filter()->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $itemSample = array_slice($inputData['items'] ?? [], 0, 3);
        $itemsJson = json_encode($itemSample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $itemCount = count($inputData['items'] ?? []);

        $rawOutput = '';
        if ($rawContext) {
            $rawOutput = "\nRaw Playwright output/stderr (last 2000 chars):\n" . substr($rawContext, -2000);
        }

        $prompt = <<<PROMPT
You are a CAPS (BVI Customs Automated Processing System) expert and automated error recovery system.

A submission to CAPS failed. Your job is to:
1. Diagnose the root cause
2. Propose CONCRETE field changes to fix the issue for retry

Errors:
- {$errorsText}

Pattern-based fixes already applied:
- {$fixesText}

Header data sent:
{$headerJson}

Items ({$itemCount} total, first 3 shown):
{$itemsJson}
{$rawOutput}
Known CAPS rules:
- Tariff codes: exactly 7 digits, no dots, matching BVI tariff schedule
- Payment method (Box 6a): must use code like "22", set via Lookup popup
- Supplier ID: must be empty string unless it's a valid CAPS-registered trader code
- Quantity units (Box 17b): must be "UNIT" — CAPS rejects "EA", "KG", etc.
- Net weight (Box 17a): required per item; use quantity as fallback
- Carrier/Voyage No (Box 3a): required, can use B/L number if not available
- Manifest No (Box 4): required, can use B/L number if not available
- Carrier ID: must be a valid registered carrier code (e.g. "ADP", "CRB")
- "Failed to parse" errors: often caused by missing required fields that trigger CAPS validation popups the script didn't handle
- All items need: tariff_number, net_weight, units, quantity, cif_value > 0

Return JSON:
{
  "diagnosis": "Clear 1-2 sentence explanation of what went wrong",
  "recommendations": ["User-facing recommendation 1", "..."],
  "severity": "critical" | "recoverable" | "minor",
  "fixes": [
    {
      "target": "header" | "item",
      "item_index": null | 0,
      "field": "field_name_in_the_data",
      "old_value": "current value or null",
      "new_value": "proposed value",
      "reason": "why this change should fix it"
    }
  ]
}

Rules for proposing fixes:
- Only propose fixes you are confident will help resolve the error
- For "Failed to parse" errors, check if required header fields (head_CarrierNo, head_ManifestNo, head_CarrierID) are empty and propose values
- For item errors, identify which items have issues (missing tariff, weight, units)
- Use existing data from the input to derive fix values (e.g. use B/L number for missing carrier/manifest)
- If no fix is possible (e.g. login failure), return empty fixes array
- Limit to 10 most important fixes
PROMPT;

        try {
            $result = $this->claude->promptForJson($prompt, 60, 2000);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            Log::error('CapsErrorRecovery: Claude fix proposal failed', ['error' => $e->getMessage()]);
            return [
                'diagnosis' => 'AI fix proposal unavailable: ' . $e->getMessage(),
                'recommendations' => ['Review the CAPS error messages manually.'],
                'fixes' => [],
            ];
        }
    }

    /**
     * Apply AI-proposed fixes to the input data.
     */
    protected function applyAiFixes(array $fixes, array $input): array
    {
        $applied = [];

        foreach ($fixes as $fix) {
            if (!is_array($fix) || empty($fix['field']) || !isset($fix['new_value'])) {
                continue;
            }

            $target = $fix['target'] ?? 'header';
            $field = $fix['field'];
            $newValue = $fix['new_value'];
            $reason = $fix['reason'] ?? '';

            if ($target === 'header') {
                $oldValue = $input['headerData'][$field] ?? null;
                $input['headerData'][$field] = $newValue;
                $applied[] = "{$field}: '{$oldValue}' → '{$newValue}'" . ($reason ? " ({$reason})" : '');
            } elseif ($target === 'item') {
                $idx = $fix['item_index'] ?? null;

                if ($idx === null) {
                    // Apply to all items
                    foreach ($input['items'] ?? [] as $i => &$item) {
                        $oldValue = $item[$field] ?? null;
                        $item[$field] = $newValue;
                    }
                    unset($item);
                    $applied[] = "All items {$field} → '{$newValue}'" . ($reason ? " ({$reason})" : '');
                } elseif (isset($input['items'][$idx])) {
                    $oldValue = $input['items'][$idx][$field] ?? null;
                    $input['items'][$idx][$field] = $newValue;
                    $applied[] = "Item {$idx} {$field}: '{$oldValue}' → '{$newValue}'" . ($reason ? " ({$reason})" : '');
                }
            }

            if (count($applied) >= 10) {
                break;
            }
        }

        return ['input' => $input, 'applied' => $applied];
    }

    /**
     * Diagnosis-only prompt (when pattern fixes already covered everything).
     */
    protected function askClaudeForDiagnosisOnly(array $errors, array $inputData, array $fixesApplied): array
    {
        $errorsText = implode("\n- ", $errors);
        $fixesText = empty($fixesApplied) ? 'None applied yet.' : implode("\n- ", $fixesApplied);

        $headerSummary = collect($inputData['headerData'] ?? [])
            ->only([
                'head_SupplierID', 'head_SupplierName', 'head_CarrierID', 'head_CarrierNo',
                'head_PortOfArrival', 'head_ManifestNo', 'head_PaymentCode_line1',
                'supplier_name', 'carrier_id', 'port_of_arrival', 'payment_method',
            ])
            ->filter()
            ->map(fn($v, $k) => "{$k}: {$v}")
            ->implode("\n");

        $itemCount = count($inputData['items'] ?? []);

        $prompt = <<<PROMPT
You are a CAPS (BVI Customs Automated Processing System) expert.

A submission to CAPS failed with these errors:
- {$errorsText}

Auto-fixes already applied:
- {$fixesText}

Header data sent:
{$headerSummary}

Number of items: {$itemCount}

Known CAPS quirks:
- Tariff codes must be exactly 7 digits matching the BVI tariff schedule
- Payment method (Box 6a) must be set via the Lookup popup, not direct JS
- Supplier ID must be empty unless it's a valid CAPS-registered trader code
- Quantity units (Box 17b) must be "UNIT" — CAPS rejects "EA"
- Net weight (Box 17a) is required; falls back to quantity if no explicit weight
- Carrier/Voyage No (Box 3a) and Manifest No (Box 4) are required

Provide a diagnosis and actionable recommendations.

Return JSON only:
{
  "diagnosis": "Clear 1-2 sentence explanation of what went wrong",
  "recommendations": ["Actionable recommendation 1", "Recommendation 2"],
  "severity": "critical" | "recoverable" | "minor"
}
PROMPT;

        try {
            $result = $this->claude->promptForJson($prompt, 30, 500);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            Log::error('CapsErrorRecovery: Claude diagnosis failed', ['error' => $e->getMessage()]);
            return [
                'diagnosis' => 'AI diagnosis unavailable: ' . $e->getMessage(),
                'recommendations' => ['Review the CAPS error messages manually and correct the input data.'],
            ];
        }
    }

    /**
     * Check if the errors are transient (worth retrying even without fixes).
     */
    protected function isTransientError(array $classified): bool
    {
        foreach ($classified as $entry) {
            if (in_array($entry['category'], ['network_error', 'parse_failure', 'unexpected_page'])) {
                return true;
            }
        }
        return false;
    }

    // ==========================================
    // Pattern-based fix methods
    // ==========================================

    protected function fixTariffCode(array $entry, array $input): ?array
    {
        $rec = $entry['record'];
        if ($rec === null || $rec === 0) {
            return null;
        }

        $itemIndex = $rec - 1;
        $items = $input['items'] ?? [];
        if (!isset($items[$itemIndex])) {
            return null;
        }

        $item = $items[$itemIndex];
        $currentCode = $item['tariff_number'] ?? '';

        $newCode = $this->dataMapper->resolveCapsTariffCodePublic($currentCode);
        if ($newCode && $newCode !== $currentCode && $newCode !== '0000000') {
            $input['items'][$itemIndex]['tariff_number'] = $newCode;
            return [
                'input' => $input,
                'description' => "Rec {$rec}: Tariff code changed from {$currentCode} to {$newCode}",
            ];
        }

        $digits = preg_replace('/[^0-9]/', '', $currentCode);
        $heading4 = substr($digits, 0, 4);

        $alternates = \App\Models\CustomsCode::where('code', 'LIKE', $heading4 . '.%')
            ->limit(5)
            ->pluck('code')
            ->toArray();

        if (!empty($alternates)) {
            $bestCode = $alternates[0];
            $sevenDigit = preg_replace('/[^0-9]/', '', $bestCode);
            $sevenDigit = str_pad($sevenDigit, 7, '0');
            $input['items'][$itemIndex]['tariff_number'] = $sevenDigit;
            return [
                'input' => $input,
                'description' => "Rec {$rec}: Tariff code changed from {$currentCode} to {$sevenDigit} (heading {$heading4} fallback)",
            ];
        }

        return null;
    }

    protected function fixPaymentMethod(array $entry, array $input): ?array
    {
        $header = $input['headerData'] ?? [];
        $current = $header['head_PaymentCode_line1'] ?? $header['payment_method'] ?? '';

        $countryId = $this->resolveCountryId($input);
        $default = '22';

        if ($countryId) {
            $country = \App\Models\Country::find($countryId);
            if ($country && $country->caps_default_payment_method) {
                $default = $country->caps_default_payment_method;
            }
        }

        if ($current !== $default) {
            $input['headerData']['head_PaymentCode_line1'] = $default;
            $input['headerData']['payment_method'] = $default;
            return [
                'input' => $input,
                'description' => "Payment method changed from '{$current}' to '{$default}'",
            ];
        }

        return null;
    }

    protected function fixMissingField(array $entry, array $input): ?array
    {
        $box = $entry['box'];
        $rec = $entry['record'];

        if ($rec === 0 || $rec === null) {
            return $this->fixMissingHeaderField($box, $input);
        }

        return $this->fixMissingItemField($box, $rec, $input);
    }

    protected function fixMissingHeaderField(?string $box, array $input): ?array
    {
        $header = $input['headerData'] ?? [];

        if ($box === '3a' && empty($header['head_CarrierNo'])) {
            $bl = $header['head_MasterBOL'] ?? $header['bill_of_lading'] ?? 'N/A';
            $input['headerData']['head_CarrierNo'] = $bl;
            return [
                'input' => $input,
                'description' => "Box 3a (Carrier/Voyage No): Set to B/L number '{$bl}'",
            ];
        }

        if ($box === '4' && empty($header['head_ManifestNo'])) {
            $bl = $header['head_MasterBOL'] ?? $header['bill_of_lading'] ?? 'N/A';
            $input['headerData']['head_ManifestNo'] = $bl;
            return [
                'input' => $input,
                'description' => "Box 4 (Manifest No): Set to B/L number '{$bl}'",
            ];
        }

        return null;
    }

    protected function fixMissingItemField(?string $box, int $rec, array $input): ?array
    {
        $itemIndex = $rec - 1;
        $items = $input['items'] ?? [];
        if (!isset($items[$itemIndex])) {
            return null;
        }

        $item = $items[$itemIndex];

        if ($box === '17a') {
            $qty = $item['quantity'] ?? $item['packages_number'] ?? '1';
            $key = "rec{$rec}_Quantity1";
            $input['items'][$itemIndex]['net_weight'] = $qty;
            $input['items'][$itemIndex][$key] = $qty;
            return [
                'input' => $input,
                'description' => "Rec {$rec}, Box 17a (Net Weight): Set to quantity {$qty}",
            ];
        }

        if ($box === '17b') {
            $input['items'][$itemIndex]['units'] = 'UNIT';
            return [
                'input' => $input,
                'description' => "Rec {$rec}, Box 17b (Units): Set to 'UNIT'",
            ];
        }

        return null;
    }

    protected function fixSupplierIdClear(array $entry, array $input): ?array
    {
        $current = $input['headerData']['head_SupplierID'] ?? '';
        if (!empty($current)) {
            $input['headerData']['head_SupplierID'] = '';
            return [
                'input' => $input,
                'description' => "Supplier ID cleared (was '{$current}') — CAPS requires a registered trader code or empty",
            ];
        }
        return null;
    }

    protected function fixUnits(array $entry, array $input): ?array
    {
        $rec = $entry['record'];
        if ($rec === null || $rec === 0) {
            return null;
        }

        $itemIndex = $rec - 1;
        if (!isset($input['items'][$itemIndex])) {
            return null;
        }

        $current = $input['items'][$itemIndex]['units'] ?? '';
        if ($current !== 'UNIT') {
            $input['items'][$itemIndex]['units'] = 'UNIT';
            return [
                'input' => $input,
                'description' => "Rec {$rec}: Units changed from '{$current}' to 'UNIT'",
            ];
        }
        return null;
    }

    protected function resolveCountryId(array $input): ?int
    {
        return $input['country_id'] ?? null;
    }

    protected function noErrorResult(array $inputData): array
    {
        return [
            'can_retry' => false,
            'fixes_applied' => [],
            'fixed_input' => $inputData,
            'diagnosis' => 'No errors detected.',
            'recommendations' => [],
            'error_categories' => [],
        ];
    }
}
