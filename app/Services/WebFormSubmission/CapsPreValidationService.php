<?php

namespace App\Services\WebFormSubmission;

use App\Models\CountryReferenceData;
use App\Models\CustomsCode;

class CapsPreValidationService
{
    protected const MONEY_TOLERANCE = 0.03;

    protected array $referenceCache = [];
    protected array $tariffKnownCache = [];
    protected array $tariffExactKnownCache = [];
    protected array $tariffHeadingKnownCache = [];
    protected array $tariffResolvedCache = [];

    public function __construct(protected WebFormDataMapper $tariffResolver)
    {
    }

    public function validateWebPayload(array $payload, ?int $countryId = null): array
    {
        $this->resetCaches();

        $errors = [];
        $warnings = [];
        $header = $payload['headerData'] ?? [];
        $items = $payload['items'] ?? [];
        $countryId = $countryId ?: ($payload['country_id'] ?? null);

        $this->validateCredentials($payload['credentials'] ?? [], $errors);
        $this->validateHeader($header, $countryId, $errors, $warnings);
        $this->validateItems($items, $countryId, $errors, $warnings);
        $this->validateHeaderTotals($header, $items, $errors, $warnings);

        return $this->report($errors, $warnings, [
            'items' => count($items),
            'header_total_freight' => $this->money($header['total_freight'] ?? 0),
            'header_total_insurance' => $this->money($header['total_insurance'] ?? 0),
            'item_total_freight' => $this->sumMoney($items, 'freight_amount'),
            'item_total_insurance' => $this->sumMoney($items, 'insurance_amount'),
        ]);
    }

    public function validateT12Preview(array $preview, ?int $countryId = null): array
    {
        $this->resetCaches();

        $errors = [];
        $warnings = [];
        $currentRecord = null;
        $records = 0;

        foreach ($preview['lines'] ?? [] as $lineIndex => $line) {
            $fields = $line['fields'] ?? str_getcsv($line['raw'] ?? '');
            $type = $fields[0] ?? '';
            $lineNumber = $lineIndex + 1;

            if ($type === 'R10') {
                $this->validateT12Header($fields, $countryId, $errors, $warnings);
                continue;
            }

            if ($type === 'R30') {
                if ($currentRecord) {
                    $this->finalizeT12Record($currentRecord, $errors, $warnings);
                }

                $records++;
                $currentRecord = [
                    'record' => $records,
                    'fob' => $this->money($fields[10] ?? 0),
                    'cif' => $this->money($fields[11] ?? 0),
                    'freight' => 0.0,
                    'insurance' => 0.0,
                ];

                $this->validateT12Item($fields, $countryId, $records, $lineNumber, $errors, $warnings);
                continue;
            }

            if ($type === 'R40' && $currentRecord) {
                $chargeCode = strtoupper(trim((string) ($fields[1] ?? '')));
                $amount = $this->money($fields[2] ?? 0);

                $this->checkReference($countryId, CountryReferenceData::TYPE_CHARGE_CODE, $chargeCode, "Record {$currentRecord['record']} charge code", true, $errors, $warnings);

                if ($chargeCode === 'FRT') {
                    $currentRecord['freight'] += $amount;
                } elseif ($chargeCode === 'INS') {
                    $currentRecord['insurance'] += $amount;
                }

                continue;
            }

            if ($type === 'R50' && $currentRecord) {
                $taxType = strtoupper(trim((string) ($fields[1] ?? '')));
                $taxValue = $this->money($fields[3] ?? 0);

                $this->checkReference($countryId, CountryReferenceData::TYPE_TAX_TYPE, $taxType, "Record {$currentRecord['record']} tax type", true, $errors, $warnings);

                if ($taxType === 'CUD') {
                    $this->assertMoneyEquals("Record {$currentRecord['record']} CUD tax value must equal CIF", $currentRecord['cif'], $taxValue, $errors);
                } elseif ($taxType === 'WHA') {
                    $this->assertMoneyEquals("Record {$currentRecord['record']} WHA tax value must equal FOB", $currentRecord['fob'], $taxValue, $errors);
                }
            }
        }

        if ($currentRecord) {
            $this->finalizeT12Record($currentRecord, $errors, $warnings);
        }

        return $this->report($errors, $warnings, [
            'items' => $records,
            'line_count' => $preview['line_count'] ?? count($preview['lines'] ?? []),
        ]);
    }

    public function validateT12Content(string $content, ?int $countryId = null): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $previewLines = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = str_getcsv($line);
            $previewLines[] = [
                'raw' => $line,
                'record_type' => $fields[0] ?? '',
                'fields' => $fields,
            ];
        }

        return $this->validateT12Preview([
            'lines' => $previewLines,
            'line_count' => count($previewLines),
        ], $countryId);
    }

    public function redactPayload(array $payload): array
    {
        if (isset($payload['credentials'])) {
            $payload['credentials'] = [
                'username' => empty($payload['credentials']['username']) ? null : '[configured]',
                'password' => empty($payload['credentials']['password']) ? null : '[configured]',
            ];
        }

        return $payload;
    }

    protected function validateCredentials(array $credentials, array &$errors): void
    {
        if (empty($credentials['username'])) {
            $errors[] = 'CAPS username is not configured.';
        }

        if (empty($credentials['password'])) {
            $errors[] = 'CAPS password is not configured.';
        }
    }

    protected function validateHeader(array $header, ?int $countryId, array &$errors, array &$warnings): void
    {
        foreach (['supplier_name' => 'Supplier name', 'arrival_date' => 'Arrival date'] as $field => $label) {
            if (empty($header[$field])) {
                $warnings[] = "{$label} is missing.";
            }
        }

        if (empty($header['manifest_number']) && empty($header['bill_of_lading'])) {
            $warnings[] = 'Manifest number and bill of lading are both missing.';
        }

        $this->checkReference($countryId, CountryReferenceData::TYPE_CARRIER, $header['carrier_id'] ?? null, 'Carrier ID', true, $errors, $warnings);
        $this->checkReference($countryId, CountryReferenceData::TYPE_PORT, $header['port_of_arrival'] ?? null, 'Port of arrival', true, $errors, $warnings);
        $this->checkReference($countryId, CountryReferenceData::TYPE_PAYMENT_METHOD, $header['payment_method'] ?? null, 'Payment method', false, $errors, $warnings);

        if (!empty($header['supplier_country'])) {
            $this->checkReference($countryId, CountryReferenceData::TYPE_COUNTRY, $header['supplier_country'], 'Supplier country', false, $errors, $warnings);
        }
    }

    protected function validateItems(array $items, ?int $countryId, array &$errors, array &$warnings): void
    {
        if (empty($items)) {
            $errors[] = 'No CAPS item records were generated.';
            return;
        }

        foreach ($items as $index => $item) {
            $itemNo = $index + 1;

            $this->checkReference($countryId, CountryReferenceData::TYPE_CPC, $item['cpc'] ?? null, "Item {$itemNo} CPC", true, $errors, $warnings);
            $this->checkReference($countryId, CountryReferenceData::TYPE_UNIT, $item['units'] ?? null, "Item {$itemNo} quantity unit", true, $errors, $warnings);
            $this->checkReference($countryId, CountryReferenceData::TYPE_CURRENCY, $item['currency'] ?? null, "Item {$itemNo} currency", false, $errors, $warnings);
            $this->checkReference($countryId, CountryReferenceData::TYPE_CHARGE_CODE, $item['freight_code'] ?? null, "Item {$itemNo} freight code", false, $errors, $warnings);
            $this->checkReference($countryId, CountryReferenceData::TYPE_CHARGE_CODE, $item['insurance_code'] ?? null, "Item {$itemNo} insurance code", false, $errors, $warnings);

            $this->validateTariff($item['tariff_number'] ?? null, "Item {$itemNo}", $errors, $warnings);

            if (empty($item['description'])) {
                $warnings[] = "Item {$itemNo} description is missing.";
            }

            if ($this->money($item['quantity'] ?? 0) <= 0) {
                $errors[] = "Item {$itemNo} quantity must be greater than zero.";
            }

            $fob = $this->money($item['fob_value'] ?? 0);
            $freight = $this->money($item['freight_amount'] ?? 0);
            $insurance = $this->money($item['insurance_amount'] ?? 0);
            $cif = $this->money($item['cif_value'] ?? 0);

            if ($fob <= 0) {
                $errors[] = "Item {$itemNo} FOB must be greater than zero.";
            }

            $this->assertMoneyEquals("Item {$itemNo} CIF must equal FOB + freight + insurance", $fob + $freight + $insurance, $cif, $errors);

            $taxType1 = $item['tax_type_1'] ?? null;
            $taxType2 = $item['tax_type_2'] ?? null;
            $this->checkReference($countryId, CountryReferenceData::TYPE_TAX_TYPE, $taxType1, "Item {$itemNo} tax type 1", false, $errors, $warnings);
            $this->checkReference($countryId, CountryReferenceData::TYPE_TAX_TYPE, $taxType2, "Item {$itemNo} tax type 2", false, $errors, $warnings);

            if (strtoupper((string) $taxType1) === 'CUD') {
                $this->assertMoneyEquals("Item {$itemNo} CUD tax value must equal CIF", $cif, $this->money($item['tax_value_1'] ?? $item['cif_value'] ?? 0), $errors);
            }

            if (strtoupper((string) $taxType2) === 'WHA') {
                $this->assertMoneyEquals("Item {$itemNo} WHA tax value must equal FOB", $fob, $this->money($item['tax_value_2'] ?? $item['fob_value'] ?? 0), $errors);
            }
        }
    }

    protected function validateHeaderTotals(array $header, array $items, array &$errors, array &$warnings): void
    {
        if (empty($items)) {
            return;
        }

        $itemFreight = $this->sumMoney($items, 'freight_amount');
        $itemInsurance = $this->sumMoney($items, 'insurance_amount');

        if (array_key_exists('total_freight', $header)) {
            $this->assertMoneyEquals('Header freight total must equal item freight total', $this->money($header['total_freight']), $itemFreight, $errors, max(0.05, count($items) * 0.01));
        }

        if (array_key_exists('total_insurance', $header)) {
            $this->assertMoneyEquals('Header insurance total must equal item insurance total', $this->money($header['total_insurance']), $itemInsurance, $errors, max(0.05, count($items) * 0.01));
        }
    }

    protected function validateT12Header(array $fields, ?int $countryId, array &$errors, array &$warnings): void
    {
        $this->checkReference($countryId, CountryReferenceData::TYPE_CARRIER, $fields[12] ?? null, 'T12 carrier ID', true, $errors, $warnings);
        $this->checkReference($countryId, CountryReferenceData::TYPE_PORT, $fields[14] ?? null, 'T12 port of arrival', true, $errors, $warnings);
        $this->checkReference($countryId, CountryReferenceData::TYPE_PAYMENT_METHOD, $fields[28] ?? null, 'T12 payment method', false, $errors, $warnings);

        if (empty($fields[15] ?? null)) {
            $warnings[] = 'T12 arrival date is missing.';
        }

        if (empty($fields[16] ?? null)) {
            $warnings[] = 'T12 manifest number is missing.';
        }
    }

    protected function validateT12Item(array $fields, ?int $countryId, int $recordNo, int $lineNumber, array &$errors, array &$warnings): void
    {
        $prefix = "Record {$recordNo} (line {$lineNumber})";

        $this->checkReference($countryId, CountryReferenceData::TYPE_CPC, $fields[1] ?? null, "{$prefix} CPC", true, $errors, $warnings);
        $this->validateTariff($fields[2] ?? null, $prefix, $errors, $warnings);
        $this->checkReference($countryId, CountryReferenceData::TYPE_UNIT, $fields[9] ?? null, "{$prefix} quantity unit", true, $errors, $warnings);

        if ($this->money($fields[8] ?? 0) <= 0) {
            $errors[] = "{$prefix} quantity must be greater than zero.";
        }

        if ($this->money($fields[10] ?? 0) <= 0) {
            $errors[] = "{$prefix} FOB must be greater than zero.";
        }
    }

    protected function finalizeT12Record(array $record, array &$errors, array &$warnings): void
    {
        $this->assertMoneyEquals(
            "Record {$record['record']} CIF must equal FOB + freight + insurance",
            $record['fob'] + $record['freight'] + $record['insurance'],
            $record['cif'],
            $errors
        );
    }

    protected function checkReference(?int $countryId, string $type, mixed $value, string $label, bool $required, array &$errors, array &$warnings): void
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            if ($required) {
                $errors[] = "{$label} is missing.";
            }
            return;
        }

        if (!$countryId) {
            $warnings[] = "Cannot validate {$label} ({$value}) because the declaration country is missing.";
            return;
        }

        $record = $this->findReference($countryId, $type, $value);

        if (!$record) {
            $errors[] = "{$label} '{$value}' is not in CAPS reference data.";
            return;
        }

        if (!$record->is_active) {
            $errors[] = "{$label} '{$value}' exists but is inactive in CAPS reference data.";
        }
    }

    protected function validateTariff(mixed $value, string $label, array &$errors, array &$warnings): void
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            $errors[] = "{$label} tariff number is missing.";
            return;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (strlen($digits) !== 7 || $raw !== $digits) {
            $errors[] = "{$label} tariff '{$raw}' must be exactly 7 digits with no dots or spaces.";
            return;
        }

        $resolved = $this->tariffResolvedCache[$digits] ??= $this->tariffResolver->resolveCapsTariffCodePublic($digits);
        if ($resolved !== $digits) {
            $warnings[] = "{$label} tariff '{$digits}' resolves to '{$resolved}'. Review before sending to CAPS.";
        }

        if (!$this->tariffExactLooksKnown($digits)) {
            if ($this->tariffHeadingLooksKnown($digits)) {
                $warnings[] = "{$label} tariff '{$digits}' matches a local heading but is not in the local exact-code table. Verify it in CAPS before submission.";
            } else {
                $warnings[] = "{$label} tariff '{$digits}' was not found as an exact local CAPS tariff code. Verify it in CAPS before submission.";
            }
        }
    }

    protected function tariffExactLooksKnown(string $sevenDigits): bool
    {
        if (array_key_exists($sevenDigits, $this->tariffExactKnownCache)) {
            return $this->tariffExactKnownCache[$sevenDigits];
        }

        $dotted7 = substr($sevenDigits, 0, 4) . '.' . substr($sevenDigits, 4);

        return $this->tariffExactKnownCache[$sevenDigits] = CustomsCode::where('code', $sevenDigits)->exists()
            || CustomsCode::where('code', $dotted7)->exists();
    }

    protected function tariffHeadingLooksKnown(string $sevenDigits): bool
    {
        if (array_key_exists($sevenDigits, $this->tariffHeadingKnownCache)) {
            return $this->tariffHeadingKnownCache[$sevenDigits];
        }

        $sixDigits = substr($sevenDigits, 0, 6);
        $dotted6 = substr($sixDigits, 0, 4) . '.' . substr($sixDigits, 4);

        return $this->tariffHeadingKnownCache[$sevenDigits] = CustomsCode::where('code', $sixDigits)->exists()
            || CustomsCode::where('code', $dotted6)->exists();
    }

    protected function findReference(int $countryId, string $type, string $value): ?CountryReferenceData
    {
        $cacheKey = $countryId . '|' . $type . '|' . strtolower($value);

        if (array_key_exists($cacheKey, $this->referenceCache)) {
            return $this->referenceCache[$cacheKey];
        }

        return $this->referenceCache[$cacheKey] = CountryReferenceData::findByLocalMatch($countryId, $type, $value);
    }

    protected function resetCaches(): void
    {
        $this->referenceCache = [];
        $this->tariffKnownCache = [];
        $this->tariffExactKnownCache = [];
        $this->tariffHeadingKnownCache = [];
        $this->tariffResolvedCache = [];
    }

    protected function assertMoneyEquals(string $message, float $expected, float $actual, array &$errors, float $tolerance = self::MONEY_TOLERANCE): void
    {
        if (abs(round($expected, 2) - round($actual, 2)) > $tolerance) {
            $errors[] = "{$message}: expected " . number_format($expected, 2, '.', '') . ', got ' . number_format($actual, 2, '.', '') . '.';
        }
    }

    protected function sumMoney(array $items, string $key): float
    {
        return round(array_sum(array_map(fn($item) => $this->money($item[$key] ?? 0), $items)), 2);
    }

    protected function money(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) ($value ?? 0)), 2);
    }

    protected function report(array $errors, array $warnings, array $summary = []): array
    {
        return [
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'summary' => array_merge($summary, [
                'error_count' => count(array_unique($errors)),
                'warning_count' => count(array_unique($warnings)),
            ]),
        ];
    }
}
