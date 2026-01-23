<?php

namespace App\Services;

use App\Models\DeclarationForm;
use App\Models\TradeContact;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class FormDataMapper
{
    protected ClaudeJsonClient $claude;

    public function __construct(ClaudeJsonClient $claude)
    {
        $this->claude = $claude;
    }

    /**
     * Map available data to extracted form fields
     * 
     * @param array $extractedFields The fields extracted from the form template
     * @param DeclarationForm $declaration The declaration form with invoice data
     * @param array $contacts Array of selected trade contacts ['shipper' => TradeContact, 'consignee' => TradeContact, etc.]
     * @return array Field mappings with values
     */
    public function mapDataToFields(
        array $extractedFields,
        DeclarationForm $declaration,
        array $contacts = []
    ): array {
        // Gather all available data
        $availableData = $this->gatherAvailableData($declaration, $contacts);
        
        // Map fields to values
        $mappings = [];
        $unmappedFields = [];

        foreach ($extractedFields as $field) {
            $fieldName = $field['field_name'] ?? '';
            $dataSource = $field['data_source'] ?? 'manual';
            $fieldLabel = $field['field_label'] ?? '';
            
            $value = $this->findValueForField($field, $availableData);
            
            $mappings[$fieldName] = [
                'field_name' => $fieldName,
                'field_label' => $fieldLabel,
                'field_type' => $field['field_type'] ?? 'text',
                'value' => $value,
                'is_required' => $field['is_required'] ?? false,
                'data_source' => $dataSource,
                'section' => $field['section'] ?? 'General',
                'is_auto_filled' => $value !== null,
                'hints' => $field['hints'] ?? null,
            ];

            if ($value === null && ($field['is_required'] ?? false)) {
                $unmappedFields[] = $field;
            }
        }

        return [
            'mappings' => $mappings,
            'unmapped_required_fields' => $unmappedFields,
            'auto_filled_count' => count(array_filter($mappings, fn($m) => $m['is_auto_filled'])),
            'total_fields' => count($mappings),
        ];
    }

    /**
     * Gather all available data from various sources
     */
    protected function gatherAvailableData(DeclarationForm $declaration, array $contacts): array
    {
        $data = [
            'shipper' => [],
            'consignee' => [],
            'broker' => [],
            'bank' => [],
            'notify_party' => [],
            'invoice' => [],
            'goods' => [],
            'shipping' => [],
            'declaration' => [],
        ];

        // Load invoice and items
        $declaration->load(['invoice.invoiceItems', 'declarationItems', 'country']);
        $invoice = $declaration->invoice;

        // Declaration data
        $data['declaration'] = [
            'form_number' => $declaration->form_number,
            'declaration_number' => $declaration->form_number,
            'declaration_date' => $declaration->declaration_date?->format('Y-m-d'),
            'total_duty' => $declaration->total_duty,
            'country' => $declaration->country?->name,
            'country_code' => $declaration->country?->code,
        ];

        // Invoice data
        if ($invoice) {
            $data['invoice'] = [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->extraction_meta['currency'] ?? 'USD',
            ];
        }

        // Goods data (from declaration items or invoice items)
        $items = $declaration->declarationItems ?? collect();
        if ($items->isEmpty() && $invoice) {
            $items = $invoice->invoiceItems ?? collect();
        }

        $goodsDescriptions = [];
        $totalQuantity = 0;
        $totalValue = 0;
        $hsCodes = [];

        foreach ($items as $item) {
            $goodsDescriptions[] = $item->description;
            $totalQuantity += $item->quantity ?? 0;
            $totalValue += $item->line_total ?? (($item->quantity ?? 0) * ($item->unit_price ?? 0));
            if ($item->hs_code ?? $item->customs_code) {
                $hsCodes[] = $item->hs_code ?? $item->customs_code;
            }
        }

        $data['goods'] = [
            'descriptions' => implode('; ', array_slice($goodsDescriptions, 0, 10)),
            'first_description' => $goodsDescriptions[0] ?? '',
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'hs_codes' => implode(', ', array_unique($hsCodes)),
            'first_hs_code' => $hsCodes[0] ?? '',
            'item_count' => count($items),
            'items' => $items->map(fn($i) => [
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'line_total' => $i->line_total ?? (($i->quantity ?? 0) * ($i->unit_price ?? 0)),
                'hs_code' => $i->hs_code ?? $i->customs_code,
            ])->toArray(),
        ];

        // Map trade contacts to data
        $contactTypeMap = [
            'shipper' => TradeContact::TYPE_SHIPPER,
            'consignee' => TradeContact::TYPE_CONSIGNEE,
            'broker' => TradeContact::TYPE_BROKER,
            'bank' => TradeContact::TYPE_BANK,
            'notify_party' => TradeContact::TYPE_NOTIFY_PARTY,
        ];

        foreach ($contactTypeMap as $key => $type) {
            if (isset($contacts[$key]) && $contacts[$key] instanceof TradeContact) {
                $data[$key] = $contacts[$key]->toFormData();
            }
        }

        return $data;
    }

    /**
     * Find the best value for a field based on its data source and label
     */
    protected function findValueForField(array $field, array $availableData): mixed
    {
        $dataSource = $field['data_source'] ?? 'manual';
        $fieldName = $field['field_name'] ?? '';
        $fieldLabel = strtolower($field['field_label'] ?? '');

        // If it's manual, we can't auto-fill
        if ($dataSource === 'manual') {
            return null;
        }

        // Get the data for this source
        $sourceData = $availableData[$dataSource] ?? [];
        
        if (empty($sourceData)) {
            return null;
        }

        // Try direct match by field name
        if (isset($sourceData[$fieldName])) {
            return $sourceData[$fieldName];
        }

        // Try to find matching key by label keywords
        return $this->findMatchingValue($fieldName, $fieldLabel, $sourceData);
    }

    /**
     * Find a matching value in source data based on field name/label
     */
    protected function findMatchingValue(string $fieldName, string $fieldLabel, array $sourceData): mixed
    {
        // Common field name mappings
        $fieldMappings = [
            // Company/name fields
            'company' => ['company_name', 'company', 'name'],
            'name' => ['company_name', 'contact_name', 'name'],
            'exporter' => ['company_name'],
            'importer' => ['company_name'],
            'shipper' => ['company_name'],
            'consignee' => ['company_name'],
            
            // Address fields
            'address' => ['full_address', 'address_line_1', 'address'],
            'street' => ['address_line_1'],
            'city' => ['city'],
            'state' => ['state_province'],
            'province' => ['state_province'],
            'postal' => ['postal_code'],
            'zip' => ['postal_code'],
            'country' => ['country', 'country_code'],
            
            // Contact fields
            'phone' => ['phone'],
            'telephone' => ['phone'],
            'fax' => ['fax'],
            'email' => ['email'],
            
            // ID fields
            'tax' => ['tax_id'],
            'vat' => ['tax_id'],
            'tin' => ['tax_id'],
            'license' => ['license_number'],
            
            // Invoice fields
            'invoice_number' => ['invoice_number'],
            'invoice_no' => ['invoice_number'],
            'invoice_date' => ['invoice_date'],
            'total' => ['total_amount', 'total_value'],
            'amount' => ['total_amount', 'total_value'],
            'currency' => ['currency'],
            
            // Declaration fields
            'declaration_number' => ['declaration_number', 'form_number'],
            'declaration_date' => ['declaration_date'],
            'duty' => ['total_duty'],
            
            // Goods fields
            'description' => ['descriptions', 'first_description'],
            'goods' => ['descriptions'],
            'quantity' => ['total_quantity'],
            'hs_code' => ['hs_codes', 'first_hs_code'],
            'tariff' => ['hs_codes', 'first_hs_code'],
            
            // Bank fields
            'bank' => ['bank_name'],
            'account' => ['bank_account'],
            'routing' => ['bank_routing'],
            'swift' => ['bank_routing'],
        ];

        // Check each mapping
        foreach ($fieldMappings as $keyword => $sourceKeys) {
            if (str_contains($fieldName, $keyword) || str_contains($fieldLabel, $keyword)) {
                foreach ($sourceKeys as $sourceKey) {
                    if (isset($sourceData[$sourceKey]) && !empty($sourceData[$sourceKey])) {
                        return $sourceData[$sourceKey];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Use AI to intelligently map remaining unmapped fields
     */
    public function aiMapRemainingFields(
        array $unmappedFields,
        array $availableData
    ): array {
        if (empty($unmappedFields)) {
            return [];
        }

        // Prepare data summary for AI
        $dataSummary = $this->prepareDataSummaryForAI($availableData);
        
        $prompt = <<<PROMPT
I have the following form fields that need to be filled, and available data.

UNMAPPED FIELDS:
{$this->formatFieldsForPrompt($unmappedFields)}

AVAILABLE DATA:
{$dataSummary}

For each field, determine if any of the available data can fill it.
Return a JSON object mapping field_name to the value that should fill it.
Only include fields where you found a match. Use null for fields that cannot be filled.

Example response:
{
  "port_of_entry": "Road Town",
  "customs_office": null
}

Return ONLY valid JSON, no explanations.
PROMPT;

        try {
            $result = $this->claude->promptForJson($prompt, 60);
            return $result ?: [];
        } catch (\Exception $e) {
            Log::warning('AI field mapping failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Format fields for AI prompt
     */
    protected function formatFieldsForPrompt(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            $lines[] = "- {$field['field_name']}: {$field['field_label']} (type: {$field['field_type']}, source: {$field['data_source']})";
        }
        return implode("\n", $lines);
    }

    /**
     * Prepare data summary for AI
     */
    protected function prepareDataSummaryForAI(array $availableData): string
    {
        $summary = [];
        
        foreach ($availableData as $source => $data) {
            if (empty($data)) continue;
            
            $summary[] = strtoupper($source) . ":";
            foreach ($data as $key => $value) {
                if ($key === 'items') continue; // Skip detailed items
                if (is_array($value)) continue;
                if ($value !== null && $value !== '') {
                    $summary[] = "  {$key}: {$value}";
                }
            }
        }

        return implode("\n", $summary);
    }

    /**
     * Get fields grouped by section with their mapped values
     */
    public function getFieldsBySection(array $mappings): array
    {
        $sections = [];
        
        foreach ($mappings as $fieldName => $mapping) {
            $section = $mapping['section'] ?? 'General';
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][] = $mapping;
        }

        return $sections;
    }
}
