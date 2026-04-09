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
        'network_error' => '/ECONNREFUSED|ETIMEDOUT|network|connection\s*refused/i',
    ];

    public function __construct(ClaudeJsonClient $claude, WebFormDataMapper $dataMapper)
    {
        $this->claude = $claude;
        $this->dataMapper = $dataMapper;
    }

    /**
     * Analyze a CAPS result and determine if we can auto-fix and retry.
     *
     * Returns:
     *   can_retry      - whether the input was patched and a retry makes sense
     *   fixes_applied  - human-readable list of fixes
     *   fixed_input    - the patched Playwright input array
     *   diagnosis      - AI-generated explanation of the failure
     *   recommendations - AI suggestions for the user
     *   error_categories - classified error types
     */
    public function analyze(array $result, array $inputData): array
    {
        $errors = $this->collectErrors($result);

        if (empty($errors)) {
            return $this->noErrorResult($inputData);
        }

        $classified = $this->classifyErrors($errors);
        $fixesApplied = [];
        $fixedInput = $inputData;

        foreach ($classified as $entry) {
            $fix = $this->tryAutoFix($entry, $fixedInput);
            if ($fix) {
                $fixedInput = $fix['input'];
                $fixesApplied[] = $fix['description'];
            }
        }

        $aiAnalysis = $this->askClaudeForDiagnosis($errors, $inputData, $fixesApplied);

        $canRetry = !empty($fixesApplied) || $this->isRetriableWithoutChanges($classified);

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

    protected function askClaudeForDiagnosis(array $errors, array $inputData, array $fixesApplied): array
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

    protected function isRetriableWithoutChanges(array $classified): bool
    {
        foreach ($classified as $entry) {
            if (in_array($entry['category'], ['network_error'])) {
                return true;
            }
        }
        return false;
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
