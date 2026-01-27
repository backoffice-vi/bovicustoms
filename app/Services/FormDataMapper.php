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
        $declaration->load(['invoice.invoiceItems', 'declarationItems', 'country', 'shipment']);
        $invoice = $declaration->invoice;
        $shipment = $declaration->shipment;

        // Declaration data
        $data['declaration'] = [
            'form_number' => $declaration->form_number,
            'declaration_number' => $declaration->form_number,
            'declaration_date' => $declaration->declaration_date?->format('Y-m-d'),
            'total_duty' => $declaration->total_duty,
            'customs_duty' => $declaration->customs_duty_total,
            'wharfage' => $declaration->wharfage_total,
            'country' => $declaration->country?->name,
            'country_code' => $declaration->country?->code,
            'fob_value' => $declaration->fob_value,
            'freight_total' => $declaration->freight_total,
            'insurance_total' => $declaration->insurance_total,
            'cif_value' => $declaration->cif_value,
            'total_packages' => $declaration->total_packages,
            'gross_weight' => $declaration->gross_weight_kg,
            'net_weight' => $declaration->net_weight_kg,
        ];

        // Shipping data from declaration or shipment
        $data['shipping'] = [
            'vessel_name' => $declaration->vessel_name ?? $shipment?->vessel_name,
            'carrier_name' => $declaration->carrier_name ?? $shipment?->carrier_name,
            'voyage_number' => $shipment?->voyage_number,
            'container_id' => $shipment?->container_id,
            'bill_of_lading' => $declaration->bill_of_lading_number ?? $shipment?->bill_of_lading_number,
            'awb_number' => $declaration->awb_number ?? $shipment?->awb_number,
            'manifest_number' => $declaration->manifest_number ?? $shipment?->manifest_number,
            'port_of_loading' => $declaration->port_of_loading ?? $shipment?->port_of_loading,
            'port_of_arrival' => $declaration->port_of_arrival ?? $shipment?->port_of_discharge,
            'port_of_discharge' => $shipment?->port_of_discharge,
            'final_destination' => $shipment?->final_destination,
            'arrival_date' => $declaration->arrival_date?->format('Y-m-d') ?? $shipment?->actual_arrival_date?->format('Y-m-d') ?? $shipment?->estimated_arrival_date?->format('Y-m-d'),
            'city_of_direct_shipment' => $shipment?->city_of_direct_shipment ?? $shipment?->port_of_loading,
            'country_of_direct_shipment' => $shipment?->countryOfDirectShipment?->name ?? $shipment?->shipperContact?->country?->name,
            'country_of_origin' => $declaration->country_of_origin ?? $shipment?->countryOfOrigin?->name ?? $shipment?->shipperContact?->country?->name,
            'total_packages' => $declaration->total_packages ?? $shipment?->total_packages,
            'package_type' => $declaration->package_type ?? $shipment?->package_type,
            'gross_weight' => $declaration->gross_weight_kg ?? $shipment?->gross_weight_kg,
            'net_weight' => $declaration->net_weight_kg ?? $shipment?->net_weight_kg,
        ];

        // Invoice data
        if ($invoice) {
            $data['invoice'] = [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->extraction_meta['currency'] ?? 'USD',
                'fob_value' => $declaration->fob_value ?? $invoice->total_amount,
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
        $itemsByTariff = [];

        foreach ($items as $item) {
            $goodsDescriptions[] = $item->description;
            $totalQuantity += $item->quantity ?? 0;
            $totalValue += $item->line_total ?? (($item->quantity ?? 0) * ($item->unit_price ?? 0));
            $hsCode = $item->hs_code ?? $item->customs_code ?? 'UNKNOWN';
            if ($hsCode) {
                $hsCodes[] = $hsCode;
                // Group items by tariff code for record counting
                if (!isset($itemsByTariff[$hsCode])) {
                    $itemsByTariff[$hsCode] = [
                        'hs_code' => $hsCode,
                        'descriptions' => [],
                        'quantity' => 0,
                        'fob_value' => 0,
                    ];
                }
                $itemsByTariff[$hsCode]['descriptions'][] = $item->description;
                $itemsByTariff[$hsCode]['quantity'] += $item->quantity ?? 0;
                $itemsByTariff[$hsCode]['fob_value'] += $item->line_total ?? (($item->quantity ?? 0) * ($item->unit_price ?? 0));
            }
        }

        // Total records = number of unique tariff codes (each becomes a RECORD on the form)
        $uniqueTariffCount = count(array_unique($hsCodes));

        $data['goods'] = [
            'descriptions' => implode('; ', array_slice($goodsDescriptions, 0, 10)),
            'first_description' => $goodsDescriptions[0] ?? '',
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'hs_codes' => implode(', ', array_unique($hsCodes)),
            'first_hs_code' => $hsCodes[0] ?? '',
            'item_count' => count($items),
            'total_records' => $uniqueTariffCount, // Number of unique tariff codes = number of RECORDs
            'tariff_groups' => array_values($itemsByTariff), // Grouped data for each RECORD
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

        // Declarant data - defaults to consignee/importer, but uses broker if broker is selected
        $declarant = $contacts['broker'] ?? $contacts['consignee'] ?? null;
        if ($declarant instanceof TradeContact) {
            $data['declaration']['declarant_name'] = $declarant->company_name;
            $data['declaration']['declarant_id'] = $declarant->customs_registration_id ?? $declarant->tax_id ?? $declarant->license_number ?? $declarant->company_name;
        }

        // Totals summary data from declaration/shipment
        $data['declaration']['total_records'] = $uniqueTariffCount; // Number of unique tariff codes
        $data['declaration']['total_freight'] = $declaration->freight_total ?? $shipment?->freight_total ?? 0;
        $data['declaration']['total_insurance'] = $declaration->insurance_total ?? $shipment?->insurance_total ?? 0;
        $data['declaration']['total_fob'] = $declaration->fob_value ?? $shipment?->fob_total ?? 0;
        $data['declaration']['total_cif'] = $declaration->cif_value ?? $shipment?->cif_total ?? 0;
        $data['declaration']['total_customs_duty'] = $declaration->customs_duty_total ?? 0;
        $data['declaration']['total_wharfage'] = $declaration->wharfage_total ?? 0;
        $data['declaration']['total_other_levies'] = $declaration->other_levies_total ?? 0;
        $data['declaration']['total_duty'] = $declaration->total_duty ?? 
            (($declaration->customs_duty_total ?? 0) + ($declaration->wharfage_total ?? 0) + ($declaration->other_levies_total ?? 0));

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

        // First, try specific data source if not manual
        if ($dataSource !== 'manual') {
            $sourceData = $availableData[$dataSource] ?? [];
            
            if (!empty($sourceData)) {
                // Try direct match by field name
                if (isset($sourceData[$fieldName])) {
                    return $sourceData[$fieldName];
                }

                // Try specific label patterns
                $value = $this->findSpecificLabelMatch($fieldLabel, $sourceData);
                if ($value !== null) {
                    return $value;
                }

                // Try keyword matching
                $value = $this->findMatchingValue($fieldName, $fieldLabel, $sourceData);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        // Even for "manual" fields, try to auto-fill based on label patterns
        // Try across all relevant data sources based on field label
        $searchOrder = $this->determineSearchOrder($fieldLabel, $availableData);
        
        // First check if any pattern explicitly blocks this field
        foreach ($searchOrder as $source) {
            $sourceData = $availableData[$source] ?? [];
            if (empty($sourceData)) {
                continue;
            }

            // Try specific label patterns - returns false if explicitly blocked
            $result = $this->findSpecificLabelMatchWithBlock($fieldLabel, $sourceData);
            if ($result === false) {
                // Explicitly blocked - don't search further
                return null;
            }
            if ($result !== null) {
                return $result;
            }

            // Try keyword matching
            $value = $this->findMatchingValue($fieldName, $fieldLabel, $sourceData);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Like findSpecificLabelMatch but returns false if pattern explicitly blocks
     */
    protected function findSpecificLabelMatchWithBlock(string $fieldLabel, array $sourceData): mixed
    {
        // Check for blocked patterns first (no data available for these)
        $blockedPatterns = [
            '/\btotal\s*(alcohol|form|fossil\s*fuel)\b/i',
        ];
        
        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $fieldLabel)) {
                return false; // Explicitly blocked
            }
        }
        
        // Special handling for "records" - only match total_records, not generic totals
        if (preg_match('/\brecords\b/i', $fieldLabel)) {
            return $sourceData['total_records'] ?? $sourceData['item_count'] ?? null;
        }
        
        return $this->findSpecificLabelMatch($fieldLabel, $sourceData);
    }

    /**
     * Determine which data sources to search based on field label
     */
    protected function determineSearchOrder(string $fieldLabel, array $availableData): array
    {
        // Declarant fields - check declaration first, then consignee/broker
        if (preg_match('/\bdeclarant\b/i', $fieldLabel)) {
            return ['declaration', 'consignee', 'broker'];
        }
        
        // Totals/summary fields - check declaration first
        if (preg_match('/\btotal\b/i', $fieldLabel)) {
            return ['declaration', 'goods', 'invoice', 'shipping'];
        }
        
        // Tariff/HS code fields - check goods first
        if (preg_match('/\b(tariff|hs\s*code|customs\s*code)\b/i', $fieldLabel)) {
            return ['goods', 'declaration'];
        }
        
        // FOB/CIF/Value fields - check declaration, invoice, goods
        if (preg_match('/\b(f\.?o\.?b|c\.?i\.?f|value|amount)\b/i', $fieldLabel)) {
            return ['declaration', 'invoice', 'goods'];
        }
        
        // Freight/Insurance fields - check declaration, shipping
        if (preg_match('/\b(freight|insurance)\b/i', $fieldLabel)) {
            return ['declaration', 'shipping'];
        }
        
        // Duty/Wharfage fields - check declaration
        if (preg_match('/\b(duty|wharfage|levy|levies)\b/i', $fieldLabel)) {
            return ['declaration'];
        }
        
        // Quantity/packages fields - check goods, declaration, shipping
        if (preg_match('/\b(quantity|packages|units|weight)\b/i', $fieldLabel)) {
            return ['goods', 'declaration', 'shipping'];
        }
        
        // Description fields - check goods
        if (preg_match('/\bdescription\b/i', $fieldLabel)) {
            return ['goods'];
        }
        
        // Default search order for manual fields
        return ['declaration', 'shipping', 'invoice', 'goods'];
    }

    /**
     * Find value based on specific label patterns (more precise than keyword matching)
     */
    protected function findSpecificLabelMatch(string $fieldLabel, array $sourceData): mixed
    {
        // Specific patterns for address fields (order matters - more specific first)
        $specificPatterns = [
            // Street/Address patterns
            '/\b(po\s*box|p\.o\.\s*box|street|address|addr)\b/i' => 'address_line_1',
            '/\b(street\s*2|address\s*2|addr\s*2|suite|unit|apt)\b/i' => 'address_line_2',
            
            // City patterns
            '/\b(city|town|island)\b/i' => 'city',
            
            // State/Province patterns
            '/\b(state|province|prov)\b/i' => 'state_province',
            
            // Postal patterns
            '/\b(zip|postal|post\s*code|postcode)\b/i' => 'postal_code',
            
            // Country patterns (only if not address)
            '/\bcountry\b(?!.*address)/i' => 'country',
            
            // Name patterns (company name is default for "name" alone)
            '/\b(company|supplier|exporter|importer|consignee|shipper)\s*name\b/i' => 'company_name',
            '/\bcontact\s*name\b/i' => 'contact_name',
            '/\bname\b(?!.*contact)/i' => 'company_name',
            
            // ID patterns
            '/\b(id|no|number|registration|reg)\b.*$/i' => function($sourceData, $fieldLabel) {
                // Try customs registration first for ID fields
                if (isset($sourceData['customs_registration_id']) && !empty($sourceData['customs_registration_id'])) {
                    return $sourceData['customs_registration_id'];
                }
                if (isset($sourceData['tax_id']) && !empty($sourceData['tax_id'])) {
                    return $sourceData['tax_id'];
                }
                if (isset($sourceData['license_number']) && !empty($sourceData['license_number'])) {
                    return $sourceData['license_number'];
                }
                // Fall back to company name for supplier/importer ID
                return $sourceData['company_name'] ?? null;
            },
            
            // Phone patterns
            '/\b(phone|tel|telephone)\b/i' => 'phone',
            '/\bfax\b/i' => 'fax',
            '/\bemail\b/i' => 'email',
            
            // Shipping patterns
            '/\b(vessel|ship)\b/i' => 'vessel_name',
            '/\bcarrier\b/i' => 'carrier_name',
            '/\bvoyage\b/i' => 'voyage_number',
            '/\bcontainer\b/i' => 'container_id',
            '/\b(bill\s*of\s*lading|b\/l|bl)\b/i' => 'bill_of_lading',
            '/\bawb\b/i' => 'awb_number',
            '/\bmanifest\b/i' => 'manifest_number',
            '/\bport\s*(of)?\s*loading\b/i' => 'port_of_loading',
            '/\bport\s*(of)?\s*(arrival|discharge)\b/i' => 'port_of_arrival',
            '/\barrival\s*date\b/i' => 'arrival_date',
            '/\borigin\b/i' => 'country_of_origin',
            '/\bdirect\s*shipment\b/i' => 'city_of_direct_shipment',
            
            // Declarant patterns
            '/\bdeclarant\s*name\b/i' => 'declarant_name',
            '/\bdeclarant\s*(id|no|number)\b/i' => 'declarant_id',
            
            // Totals summary patterns (order matters - most specific first)
            '/\btotal\s*(no\.?\s*of\s*)?records\b/i' => 'total_records',
            '/\b(no\.?\s*of\s*)?records\b/i' => 'total_records',
            '/\btotal\s*freight\b/i' => 'total_freight',
            '/\btotal\s*insurance\b/i' => 'total_insurance',
            '/\btotal\s*customs\s*duty\b/i' => 'total_customs_duty',
            '/\btotal\s*wharfage\b/i' => 'total_wharfage',
            '/\btotal\s*duty\b(?!\s*(customs|wharfage|alcohol|fuel|form))/i' => 'total_duty',
            '/\btotal\s*cif\b/i' => 'total_cif',
            '/\btotal\s*fob\b/i' => 'total_fob',
            // These levy fields have no data currently - don't match them
            '/\btotal\s*(alcohol|form|fossil\s*fuel)\b/i' => null,
            
            // Item/Tariff details patterns  
            '/\b(tariff|hs)\s*(no\.?|code|number)\b/i' => 'first_hs_code',
            '/\bf\.?o\.?b\.?\s*value\b/i' => 'fob_value',
            '/\bc\.?i\.?f\.?\s*value\b/i' => 'cif_value',
            '/\bquantity\s*(\/\s*units)?\b/i' => 'total_quantity',
            '/\bamount\b/i' => 'total_value',
        ];

        foreach ($specificPatterns as $pattern => $sourceKey) {
            if (preg_match($pattern, $fieldLabel)) {
                // Null means we explicitly don't want to match this field
                if ($sourceKey === null) {
                    return null;
                }
                if (is_callable($sourceKey)) {
                    return $sourceKey($sourceData, $fieldLabel);
                }
                if (isset($sourceData[$sourceKey]) && !empty($sourceData[$sourceKey])) {
                    return $sourceData[$sourceKey];
                }
            }
        }

        return null;
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
            'supplier' => ['company_name'],
            
            // Address fields
            'address' => ['full_address', 'address_line_1', 'address'],
            'street' => ['address_line_1'],
            'city' => ['city'],
            'state' => ['state_province'],
            'province' => ['state_province'],
            'postal' => ['postal_code'],
            'zip' => ['postal_code'],
            'country' => ['country', 'country_code', 'country_of_origin', 'country_of_direct_shipment'],
            
            // Contact fields
            'phone' => ['phone'],
            'telephone' => ['phone'],
            'fax' => ['fax'],
            'email' => ['email'],
            
            // ID fields
            'tax' => ['tax_id', 'customs_registration_id'],
            'vat' => ['tax_id'],
            'tin' => ['tax_id', 'customs_registration_id'],
            'license' => ['license_number'],
            'registration' => ['customs_registration_id', 'license_number'],
            'importer_id' => ['customs_registration_id', 'tax_id'],
            'declarant' => ['customs_registration_id', 'license_number'],
            
            // Invoice fields
            'invoice_number' => ['invoice_number'],
            'invoice_no' => ['invoice_number'],
            'invoice_date' => ['invoice_date'],
            'total' => ['total_amount', 'total_value', 'cif_value'],
            'amount' => ['total_amount', 'total_value'],
            'currency' => ['currency'],
            'fob' => ['fob_value', 'total_amount'],
            'freight' => ['freight_total'],
            'insurance' => ['insurance_total'],
            'cif' => ['cif_value'],
            
            // Declaration fields
            'declaration_number' => ['declaration_number', 'form_number'],
            'declaration_date' => ['declaration_date'],
            'duty' => ['total_duty', 'customs_duty'],
            'wharfage' => ['wharfage'],
            
            // Goods fields
            'description' => ['descriptions', 'first_description'],
            'goods' => ['descriptions'],
            'quantity' => ['total_quantity'],
            'hs_code' => ['hs_codes', 'first_hs_code'],
            'tariff' => ['hs_codes', 'first_hs_code'],
            'packages' => ['total_packages'],
            'package' => ['total_packages', 'package_type'],
            'weight' => ['gross_weight', 'net_weight'],
            'gross' => ['gross_weight'],
            'net' => ['net_weight'],
            
            // Shipping fields
            'vessel' => ['vessel_name', 'carrier_name'],
            'carrier' => ['carrier_name', 'vessel_name'],
            'voyage' => ['voyage_number'],
            'container' => ['container_id'],
            'bill_of_lading' => ['bill_of_lading'],
            'b_l' => ['bill_of_lading'],
            'bl_' => ['bill_of_lading'],
            'awb' => ['awb_number'],
            'manifest' => ['manifest_number'],
            'port_of_loading' => ['port_of_loading'],
            'port_of_arrival' => ['port_of_arrival', 'port_of_discharge'],
            'port_of_discharge' => ['port_of_discharge', 'port_of_arrival'],
            'arrival' => ['arrival_date', 'port_of_arrival'],
            'destination' => ['final_destination', 'port_of_discharge'],
            'origin' => ['country_of_origin', 'port_of_loading'],
            'direct_shipment' => ['city_of_direct_shipment', 'country_of_direct_shipment'],
            
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
