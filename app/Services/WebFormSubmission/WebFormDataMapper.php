<?php

namespace App\Services\WebFormSubmission;

use App\Models\DeclarationForm;
use App\Models\WebFormTarget;
use App\Models\WebFormFieldMapping;
use App\Services\ClaudeJsonClient;
use Illuminate\Support\Facades\Log;

/**
 * WebFormDataMapper
 * 
 * Maps data from a DeclarationForm to the field structure expected by
 * an external web form based on stored field mappings.
 * Uses Claude AI for intelligent field discovery when standard paths fail.
 */
class WebFormDataMapper
{
    protected ?ClaudeJsonClient $claude = null;
    
    public function __construct(?ClaudeJsonClient $claude = null)
    {
        $this->claude = $claude;
    }

    /**
     * Map declaration data to target web form fields
     */
    public function mapDeclarationToTarget(DeclarationForm $declaration, WebFormTarget $target): array
    {
        // Load related data
        $declaration->load([
            'invoice.invoiceItems',
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
            'country',
            'declarationItems',
        ]);

        $fields = [];
        $mappings = [];
        $unmappedRequired = [];

        // Get all field mappings for this target
        foreach ($target->pages as $page) {
            foreach ($page->activeFieldMappings as $mapping) {
                $value = $mapping->getValueFromDeclaration($declaration);

                $fieldData = [
                    'name' => $mapping->web_field_label,
                    'field_name' => $mapping->web_field_name,
                    'field_id' => $mapping->web_field_id,
                    'selectors' => $mapping->getSelectorsForPlaywright(),
                    'type' => $mapping->field_type,
                    'value' => $value,
                    'required' => $mapping->is_required,
                    'page_id' => $page->id,
                    'page_url' => $page->url_pattern,
                    'section' => $mapping->section,
                    'tab_order' => $mapping->tab_order,
                ];

                // Use the primary selector as the key
                $key = $mapping->web_field_name ?? $mapping->web_field_id ?? $mapping->primary_selector;
                $fields[$key] = $fieldData;

                $mappings[] = [
                    'mapping_id' => $mapping->id,
                    'local_field' => $mapping->local_field,
                    'web_field' => $key,
                    'value' => $value,
                    'source' => $mapping->source_description,
                ];

                // Track unmapped required fields
                if ($mapping->is_required && $value === null) {
                    $unmappedRequired[] = [
                        'field' => $mapping->web_field_label,
                        'local_field' => $mapping->local_field,
                    ];
                }
            }
        }

        return [
            'fields' => $fields,
            'mappings' => $mappings,
            'unmapped_required' => $unmappedRequired,
            'total_fields' => count($fields),
            'filled_fields' => count(array_filter($fields, fn($f) => $f['value'] !== null)),
        ];
    }

    /**
     * Map data for a specific page only
     */
    public function mapDeclarationToPage(DeclarationForm $declaration, $page): array
    {
        $declaration->load([
            'invoice.invoiceItems',
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
            'country',
        ]);

        $fields = [];

        foreach ($page->activeFieldMappings as $mapping) {
            $value = $mapping->getValueFromDeclaration($declaration);

            $key = $mapping->web_field_name ?? $mapping->web_field_id ?? $mapping->primary_selector;
            $fields[$key] = [
                'name' => $mapping->web_field_label,
                'selectors' => $mapping->getSelectorsForPlaywright(),
                'type' => $mapping->field_type,
                'value' => $value,
                'required' => $mapping->is_required,
            ];
        }

        return $fields;
    }

    /**
     * Preview mapping without actually submitting
     * Shows what values would be sent for each field
     */
    public function previewMapping(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $mapped = $this->mapDeclarationToTarget($declaration, $target);

        $preview = [];
        foreach ($target->pages as $page) {
            $pagePreview = [
                'page_name' => $page->name,
                'page_type' => $page->page_type,
                'url' => $page->full_url,
                'fields' => [],
            ];

            foreach ($page->activeFieldMappings as $mapping) {
                $value = $mapping->getValueFromDeclaration($declaration);

                $pagePreview['fields'][] = [
                    'label' => $mapping->web_field_label,
                    'type' => $mapping->field_type_label,
                    'value' => $value,
                    'source' => $mapping->source_description,
                    'required' => $mapping->is_required,
                    'has_value' => $value !== null,
                    'section' => $mapping->section,
                ];
            }

            $preview[] = $pagePreview;
        }

        return [
            'target_name' => $target->name,
            'target_url' => $target->base_url,
            'pages' => $preview,
            'summary' => [
                'total_fields' => $mapped['total_fields'],
                'filled_fields' => $mapped['filled_fields'],
                'unmapped_required' => $mapped['unmapped_required'],
                'ready_to_submit' => empty($mapped['unmapped_required']),
            ],
        ];
    }

    /**
     * Validate that all required fields have values
     */
    public function validateMapping(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $mapped = $this->mapDeclarationToTarget($declaration, $target);
        
        $errors = [];
        
        foreach ($mapped['unmapped_required'] as $field) {
            $errors[] = "Required field '{$field['field']}' has no value (source: {$field['local_field']})";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => [], // Could add warnings for optional fields
        ];
    }

    /**
     * Build a simple flat array of field => value for Playwright
     * Used for simpler submission scripts
     */
    public function buildSimpleFieldMap(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $mapped = $this->mapDeclarationToTarget($declaration, $target);
        
        $simple = [];
        foreach ($mapped['fields'] as $key => $field) {
            if ($field['value'] !== null) {
                $simple[$key] = $field['value'];
            }
        }

        return $simple;
    }

    /**
     * Build CAPS-specific data structure for multi-item submissions
     * Separates header-level fields from item-level fields
     * Uses AI assistance when critical data is missing
     */
    public function buildCapsSubmissionData(DeclarationForm $declaration, WebFormTarget $target, bool $useAI = true): array
    {
        $declaration->load([
            'invoice.invoiceItems',
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
            'country',
            'declarationItems',
            'filledForms',
        ]);

        // Get header data
        $headerData = $this->extractHeaderData($declaration, $target);
        
        // Get item data for each declaration item
        $items = $this->extractItemsData($declaration, $target);

        // Use AI to fill missing critical fields
        $aiResult = null;
        if ($useAI) {
            $aiResult = $this->aiAssistCapsMapping($declaration, $headerData, $items);
            $headerData = $aiResult['headerData'];
            $items = $aiResult['items'];
        }

        return [
            'headerData' => $headerData,
            'items' => $items,
            'credentials' => $target->getPlaywrightCredentials(),
            'loginUrl' => $target->full_login_url,
            'ai_assisted' => $aiResult['ai_assisted'] ?? false,
            'ai_notes' => $aiResult['ai_notes'] ?? null,
        ];
    }

    /**
     * Extract header-level data (sections 1-10 of CAPS form)
     */
    protected function extractHeaderData(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $shipment = $declaration->shipment;
        $shipper = $declaration->shipperContact ?? $shipment?->shipperContact;
        $consignee = $declaration->consigneeContact ?? $shipment?->consigneeContact;
        $invoice = $declaration->invoice;

        // Calculate totals
        $totalFob = 0;
        $totalFreight = $shipment?->freight_cost ?? 0;
        $totalInsurance = $shipment?->insurance_cost ?? 0;

        // Get items from JSON or relation
        $items = $declaration->items ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }
        
        foreach ($items as $item) {
            $totalFob += floatval($item['value'] ?? $item['fob_value'] ?? 0);
        }

        // Format arrival date as DD/MM/YYYY for CAPS
        $arrivalDate = $shipment?->arrival_date;
        if ($arrivalDate) {
            $arrivalDate = \Carbon\Carbon::parse($arrivalDate)->format('d/m/Y');
        }

        return [
            'td_type' => 'Import', // Default to Import
            'trader_reference' => $declaration->reference_number ?? $shipment?->bill_of_lading,
            
            // Supplier (Shipper)
            'supplier_name' => $shipper?->company_name ?? $shipper?->name,
            'supplier_street' => $shipper?->address_line_1,
            'supplier_city' => $shipper?->city,
            'supplier_country' => $shipper?->country?->iso2 ?? $shipper?->country_code ?? 'US',
            
            // Transport
            'carrier_id' => $this->mapCarrier($shipment?->carrier),
            'port_of_arrival' => $this->mapPort($shipment?->port_of_arrival) ?? 'PP',
            'arrival_date' => $arrivalDate,
            
            // Manifest
            'manifest_number' => $shipment?->manifest_number,
            'packages_count' => $declaration->total_packages ?? count($items),
            'bill_of_lading' => $shipment?->bill_of_lading,
            
            // Shipment origin
            'city_of_shipment' => $shipment?->port_of_loading,
            'country_of_shipment' => $shipper?->country?->iso2 ?? 'US',
            'country_of_origin_shipment' => $shipper?->country?->iso2 ?? 'US',
            
            // Totals
            'total_freight' => number_format($totalFreight, 2, '.', ''),
            'total_insurance' => number_format($totalInsurance, 2, '.', ''),
            'freight_prorated' => count($items) > 1,
            'insurance_prorated' => count($items) > 1,
        ];
    }

    /**
     * Extract item-level data for each declaration item
     */
    protected function extractItemsData(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $items = [];
        $shipment = $declaration->shipment;
        
        // Get items from JSON field
        $declarationItems = $declaration->items ?? [];
        if (is_string($declarationItems)) {
            $declarationItems = json_decode($declarationItems, true) ?? [];
        }

        // If no items in JSON, try from filled form
        if (empty($declarationItems)) {
            $filledForm = $declaration->filledForms()->latest()->first();
            if ($filledForm && !empty($filledForm->data['tariff_groups'])) {
                $declarationItems = $filledForm->data['tariff_groups'];
            }
        }

        // Calculate totals for proration
        $totalFob = array_sum(array_map(fn($i) => floatval($i['value'] ?? $i['fob_value'] ?? 0), $declarationItems));
        $totalFreight = floatval($shipment?->freight_cost ?? 0);
        $totalInsurance = floatval($shipment?->insurance_cost ?? 0);

        foreach ($declarationItems as $index => $item) {
            $fobValue = floatval($item['value'] ?? $item['fob_value'] ?? 0);
            
            // Prorate freight and insurance based on FOB value proportion
            $proportion = $totalFob > 0 ? $fobValue / $totalFob : 0;
            $itemFreight = $totalFreight * $proportion;
            $itemInsurance = $totalInsurance * $proportion;
            $cifValue = $fobValue + $itemFreight + $itemInsurance;

            // Get tariff code
            $tariffCode = $item['hs_code'] ?? $item['tariff_code'] ?? $item['customs_code'] ?? '';
            $tariffCode = preg_replace('/[^0-9]/', '', $tariffCode); // Remove non-digits

            $items[] = [
                'cpc' => $item['cpc'] ?? 'C400', // Default CPC for import
                'tariff_number' => $tariffCode,
                'country_of_origin' => $item['country_of_origin'] ?? 'US',
                'packages_number' => $item['quantity'] ?? 1,
                'packages_type' => $this->mapPackageType($item['unit'] ?? 'EA'),
                'description' => $item['description'] ?? '',
                'net_weight' => $item['weight'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'units' => $this->mapUnit($item['unit'] ?? 'EA'),
                'fob_value' => number_format($fobValue, 2, '.', ''),
                'currency' => $item['currency'] ?? 'USD', // Default to USD
                'exchange_rate' => '1.00', // BVI uses USD at 1:1
                'freight_code' => 'FRT',
                'freight_amount' => number_format($itemFreight, 2, '.', ''),
                'insurance_code' => 'INS',
                'insurance_amount' => number_format($itemInsurance, 2, '.', ''),
                'cif_value' => number_format($cifValue, 2, '.', ''),
                'tax_type_1' => 'CUD', // Customs Duty
                'tax_type_2' => 'WHF', // Wharfage
            ];
        }

        return $items;
    }

    /**
     * Map carrier name to CAPS carrier code
     */
    protected function mapCarrier(?string $carrier): string
    {
        if (!$carrier) return '';
        
        // Common carrier mappings
        $mappings = [
            'tropical' => 'TRP',
            'seaboard' => 'SMC',
            'crowley' => 'CMT',
            'american' => 'AA',
            'fedex' => 'FX',
            'ups' => 'UPS',
            'dhl' => 'DHL',
        ];

        $lower = strtolower($carrier);
        foreach ($mappings as $key => $code) {
            if (str_contains($lower, $key)) {
                return $code;
            }
        }

        return strtoupper(substr($carrier, 0, 3));
    }

    /**
     * Map port name to CAPS port code
     */
    protected function mapPort(?string $port): string
    {
        if (!$port) return 'PP';
        
        $mappings = [
            'road town' => 'PP',
            'tortola' => 'PP',
            'west end' => 'WE',
            'virgin gorda' => 'VG',
            'beef island' => 'BI',
        ];

        $lower = strtolower($port);
        foreach ($mappings as $key => $code) {
            if (str_contains($lower, $key)) {
                return $code;
            }
        }

        return 'PP';
    }

    /**
     * Map package type to CAPS code
     */
    protected function mapPackageType(?string $type): string
    {
        if (!$type) return 'CTN';
        
        $mappings = [
            'carton' => 'CTN',
            'ctn' => 'CTN',
            'box' => 'BOX',
            'pallet' => 'PLT',
            'bag' => 'BAG',
            'case' => 'CS',
            'each' => 'EA',
            'piece' => 'PCS',
        ];

        $lower = strtolower($type);
        foreach ($mappings as $key => $code) {
            if (str_contains($lower, $key)) {
                return $code;
            }
        }

        return strtoupper(substr($type, 0, 3));
    }

    /**
     * Map unit to CAPS unit code
     */
    protected function mapUnit(?string $unit): string
    {
        if (!$unit) return 'EA';
        
        $mappings = [
            'each' => 'EA',
            'piece' => 'PCS',
            'kg' => 'KG',
            'lb' => 'LB',
            'liter' => 'LT',
            'gallon' => 'GAL',
            'dozen' => 'DOZ',
        ];

        $lower = strtolower($unit);
        foreach ($mappings as $key => $code) {
            if (str_contains($lower, $key)) {
                return $code;
            }
        }

        return strtoupper(substr($unit, 0, 2));
    }

    /**
     * Use Claude AI to analyze declaration and fill missing CAPS fields
     */
    public function aiAssistCapsMapping(DeclarationForm $declaration, array $headerData, array $items): array
    {
        if (!$this->claude) {
            $this->claude = app(ClaudeJsonClient::class);
        }

        // Check which critical fields are missing
        $missingFields = [];
        $criticalHeaderFields = ['supplier_name', 'carrier_id', 'arrival_date', 'bill_of_lading'];
        foreach ($criticalHeaderFields as $field) {
            if (empty($headerData[$field])) {
                $missingFields[] = $field;
            }
        }

        // Check if items have required data
        $itemsMissingData = false;
        foreach ($items as $item) {
            if (empty($item['tariff_number']) || empty($item['description']) || empty($item['fob_value'])) {
                $itemsMissingData = true;
                break;
            }
        }

        // If nothing critical is missing, return as-is
        if (empty($missingFields) && !$itemsMissingData) {
            return ['headerData' => $headerData, 'items' => $items, 'ai_assisted' => false];
        }

        Log::info('CapsMapper: Using AI to fill missing fields', ['missing' => $missingFields]);

        // Gather all available declaration data for AI analysis
        $declarationData = $this->gatherDeclarationData($declaration);

        $prompt = <<<PROMPT
You are helping map a customs declaration to the BVI CAPS system fields.

**Available Declaration Data:**
```json
{$declarationData}
```

**Current Header Mapping (some fields may be empty):**
```json
{json_encode($headerData, JSON_PRETTY_PRINT)}
```

**Current Items (may need enhancement):**
```json
{json_encode($items, JSON_PRETTY_PRINT)}
```

**Missing/Empty Fields to Fill:**
- {implode("\n- ", $missingFields ?: ['Review all fields'])}

**Instructions:**
1. Analyze the available declaration data to find values for missing fields
2. Look for data in: reference_number, notes, items JSON, any attached records
3. Use reasonable defaults when exact data isn't available:
   - supplier_country: default to country from shipper or 'US'
   - carrier_id: try to identify from any shipping references
   - port_of_arrival: default to 'PP' (Port Purcell, Road Town)
   - cpc: default to 'C400' for imports
4. For items, ensure each has: description, tariff_number, quantity, fob_value

**Return JSON only:**
{
  "headerData": { ...complete header with filled values... },
  "items": [ ...complete items array... ],
  "ai_notes": "Brief explanation of what was filled/inferred"
}
PROMPT;

        try {
            $result = $this->claude->promptForJson($prompt, 60, 2000);

            if (!empty($result['headerData'])) {
                $headerData = array_merge($headerData, array_filter($result['headerData']));
            }
            if (!empty($result['items'])) {
                $items = $result['items'];
            }

            Log::info('CapsMapper: AI assisted mapping complete', [
                'notes' => $result['ai_notes'] ?? 'No notes',
            ]);

            return [
                'headerData' => $headerData,
                'items' => $items,
                'ai_assisted' => true,
                'ai_notes' => $result['ai_notes'] ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('CapsMapper: AI assistance failed', ['error' => $e->getMessage()]);
            return ['headerData' => $headerData, 'items' => $items, 'ai_assisted' => false];
        }
    }

    /**
     * Gather all available data from a declaration for AI analysis
     */
    protected function gatherDeclarationData(DeclarationForm $declaration): string
    {
        $data = [
            'id' => $declaration->id,
            'reference_number' => $declaration->reference_number,
            'status' => $declaration->status,
            'total_packages' => $declaration->total_packages,
            'total_value' => $declaration->total_value,
            'notes' => $declaration->notes,
            'items_json' => $declaration->items,
        ];

        // Add shipment data if available
        if ($declaration->shipment) {
            $data['shipment'] = [
                'bill_of_lading' => $declaration->shipment->bill_of_lading,
                'carrier' => $declaration->shipment->carrier,
                'vessel_name' => $declaration->shipment->vessel_name,
                'arrival_date' => $declaration->shipment->arrival_date,
                'port_of_arrival' => $declaration->shipment->port_of_arrival,
                'freight_cost' => $declaration->shipment->freight_cost,
                'insurance_cost' => $declaration->shipment->insurance_cost,
            ];
        }

        // Add shipper data if available
        $shipper = $declaration->shipperContact ?? $declaration->shipment?->shipperContact;
        if ($shipper) {
            $data['shipper'] = [
                'company_name' => $shipper->company_name,
                'name' => $shipper->name,
                'address' => $shipper->address_line_1,
                'city' => $shipper->city,
                'country' => $shipper->country?->name ?? $shipper->country_code,
            ];
        }

        // Add invoice data if available
        if ($declaration->invoice) {
            $data['invoice'] = [
                'invoice_number' => $declaration->invoice->invoice_number,
                'total_amount' => $declaration->invoice->total_amount,
                'currency' => $declaration->invoice->currency,
            ];

            // Add invoice items
            if ($declaration->invoice->invoiceItems) {
                $data['invoice_items'] = $declaration->invoice->invoiceItems->map(fn($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'hs_code' => $i->hs_code,
                ])->toArray();
            }
        }

        // Add filled form data if available
        $filledForm = $declaration->filledForms()->latest()->first();
        if ($filledForm) {
            $data['filled_form'] = $filledForm->data;
        }

        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
