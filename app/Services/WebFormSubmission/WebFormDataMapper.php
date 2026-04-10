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
            $isLoginPage = $page->page_type === 'login';

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

                // Track unmapped required fields (skip login page fields — those are filled from stored credentials)
                if ($mapping->is_required && $value === null && !$isLoginPage) {
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
        $warnings = [];
        
        foreach ($mapped['unmapped_required'] as $field) {
            $source = $field['local_field'] ?? '';
            $label = $field['field'];
            $errors[] = "Required field '{$label}' has no value (source: {$source})";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
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
        $totalFreight = floatval($shipment?->freight_total ?? $shipment?->freight_cost ?? 0);
        $totalInsurance = floatval($shipment?->insurance_total ?? $shipment?->insurance_cost ?? 0);

        // Gather items from all available sources
        $resolvedItems = $this->resolveDeclarationItems($declaration);
        
        foreach ($resolvedItems as $item) {
            $totalFob += floatval($item['fob_value'] ?? $item['value'] ?? 0);
        }

        // If total FOB is zero, try from invoice total
        if ($totalFob <= 0 && $invoice) {
            $totalFob = floatval($invoice->total_amount ?? 0);
        }

        // Format arrival date as DD/MM/YYYY for CAPS
        $arrivalDate = $shipment?->arrival_date;
        if ($arrivalDate) {
            $arrivalDate = \Carbon\Carbon::parse($arrivalDate)->format('d/m/Y');
        }

        // Resolve B/L number: shipment field, then shipping document number
        $billOfLading = $shipment?->bill_of_lading;
        if (empty($billOfLading) && $shipment) {
            $transportDoc = $shipment->shippingDocuments
                ->first(fn($doc) => $doc->isPrimaryTransportDocument());
            $billOfLading = $transportDoc?->document_number;
        }

        // Resolve carrier from shipment or vessel info
        $carrierRaw = $shipment?->carrier;
        if (empty($carrierRaw) && $shipment?->vessel_name) {
            $carrierRaw = $shipment->vessel_name;
        }

        // Resolve country from shipper contact, validate against CAPS codes
        $shipperCountryRaw = $shipper?->country?->iso2 ?? $shipper?->country_code;
        if (empty($shipperCountryRaw) && $shipper?->city) {
            $cityLower = strtolower($shipper->city);
            if (str_contains($cityLower, 'san juan') || str_contains($cityLower, 'puerto rico')) {
                $shipperCountryRaw = 'US';
            }
        }
        $shipperCountry = $this->mapCountry($shipperCountryRaw ?: 'US');

        return [
            'td_type' => 'Import',
            'trader_reference' => $declaration->reference_number ?? $billOfLading,
            
            // Supplier (Shipper)
            'supplier_id' => '', // Left empty — CAPS expects a registered trader code, not a reference number
            'supplier_name' => $shipper?->company_name ?? $shipper?->name,
            'supplier_street' => $shipper?->address_line_1,
            'supplier_city' => $shipper?->city,
            'supplier_country' => $shipperCountry,
            
            // Transport
            'carrier_id' => $this->mapCarrier($carrierRaw),
            'carrier_number' => $shipment?->voyage_number ?? '',
            'port_of_arrival' => $this->mapPort($shipment?->port_of_arrival) ?? 'PP',
            'arrival_date' => $arrivalDate,
            
            // Manifest
            'manifest_number' => $shipment?->manifest_number ?? $billOfLading ?? '',
            'packages_count' => max(
                count($resolvedItems),
                intval($declaration->total_packages ?? 0),
                array_sum(array_map(fn($i) => max(1, intval($i['quantity'] ?? 1)), $resolvedItems))
            ),
            'bill_of_lading' => $billOfLading,
            
            // Shipment origin
            'city_of_shipment' => $shipment?->port_of_loading ?? $shipper?->city,
            'country_of_shipment' => $shipperCountry,
            'country_of_origin_shipment' => $shipperCountry,
            
            // Payment (Box 6a) — country-level default, falls back to 22 (Cheque - USD)
            'payment_method' => $country->caps_default_payment_method ?? '22',
            
            // Totals
            'total_freight' => number_format($totalFreight, 2, '.', ''),
            'total_insurance' => number_format($totalInsurance, 2, '.', ''),
            'freight_prorated' => count($resolvedItems) > 1,
            'insurance_prorated' => count($resolvedItems) > 1,
        ];
    }

    /**
     * Resolve declaration items from all available sources
     * Priority: items JSON > filledForms tariff_groups > declarationItems relation > invoiceItems
     */
    protected function resolveDeclarationItems(DeclarationForm $declaration): array
    {
        // Source 1: items JSON field
        $rawItems = $declaration->items ?? [];
        if (is_string($rawItems)) {
            $rawItems = json_decode($rawItems, true) ?? [];
        }
        if (!empty($rawItems)) {
            return $rawItems;
        }

        // Source 2: filled form tariff_groups
        $filledForm = $declaration->filledForms()->latest()->first();
        if ($filledForm && !empty($filledForm->data['tariff_groups'])) {
            return $filledForm->data['tariff_groups'];
        }

        // Source 3: declarationItems relation (DeclarationFormItem models)
        $declItems = $declaration->declarationItems;
        if ($declItems && $declItems->isNotEmpty()) {
            return $declItems->map(fn($di) => [
                'description' => $di->description,
                'quantity' => $di->quantity,
                'fob_value' => $di->line_total ?? ($di->quantity * $di->unit_price),
                'hs_code' => $di->hs_code,
                'country_of_origin' => null,
                'unit' => null,
                'weight' => null,
                'unit_price' => $di->unit_price,
                'currency' => $di->currency,
            ])->toArray();
        }

        // Source 4: invoice items as last resort
        $allInvoices = $declaration->getAllInvoices();
        $invoiceItems = [];
        foreach ($allInvoices as $invoice) {
            foreach ($invoice->invoiceItems as $ii) {
                $invoiceItems[] = [
                    'description' => $ii->description,
                    'quantity' => $ii->quantity,
                    'fob_value' => $ii->quantity * $ii->unit_price,
                    'hs_code' => $ii->customs_code ?? $ii->hs_code,
                    'country_of_origin' => $ii->country_of_origin,
                    'unit' => $ii->unit,
                    'unit_price' => $ii->unit_price,
                ];
            }
        }

        return $invoiceItems;
    }

    /**
     * Extract item-level data for each declaration item
     */
    protected function extractItemsData(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $items = [];
        $shipment = $declaration->shipment;
        
        $declarationItems = $this->resolveDeclarationItems($declaration);

        $country = $declaration->country ?? \App\Models\Country::find(1);
        $shouldGroup = $country?->caps_group_items ?? config('services.caps.group_items_by_hs_code', true);
        if ($shouldGroup) {
            $declarationItems = $this->groupItemsByHsCode($declarationItems);
        }

        // Calculate totals for proration
        $totalFob = array_sum(array_map(fn($i) => floatval($i['fob_value'] ?? $i['value'] ?? 0), $declarationItems));
        $totalFreight = floatval($shipment?->freight_total ?? $shipment?->freight_cost ?? 0);
        $totalInsurance = floatval($shipment?->insurance_total ?? $shipment?->insurance_cost ?? 0);

        // If total FOB is zero, calculate from unit prices
        if ($totalFob <= 0) {
            $totalFob = array_sum(array_map(function ($i) {
                $qty = floatval($i['quantity'] ?? 1);
                $price = floatval($i['unit_price'] ?? 0);
                return $qty * $price;
            }, $declarationItems));
        }

        foreach ($declarationItems as $index => $item) {
            $fobValue = floatval($item['fob_value'] ?? $item['value'] ?? 0);
            
            // Calculate from qty * unit_price if no explicit FOB
            if ($fobValue <= 0 && !empty($item['unit_price'])) {
                $fobValue = floatval($item['quantity'] ?? 1) * floatval($item['unit_price']);
            }
            
            // Prorate freight and insurance based on FOB value proportion
            $proportion = $totalFob > 0 ? $fobValue / $totalFob : 0;
            $itemFreight = $totalFreight * $proportion;
            $itemInsurance = $totalInsurance * $proportion;
            $cifValue = $fobValue + $itemFreight + $itemInsurance;

            // Resolve HS code to a valid 7-digit BVI tariff code
            $rawHsCode = $item['hs_code'] ?? $item['tariff_code'] ?? $item['customs_code'] ?? '';
            $tariffCode = $this->resolveCapsTariffCode($rawHsCode);

            $qty = intval($item['quantity'] ?? 1);
            $weight = floatval($item['weight'] ?? 0);
            if ($weight <= 0) {
                $weight = $qty;
            }

            $items[] = [
                'cpc' => $item['cpc'] ?? 'C400',
                'tariff_number' => $tariffCode,
                'country_of_origin' => $this->mapCountry($item['country_of_origin'] ?? 'US'),
                'packages_number' => $qty,
                'packages_type' => $this->mapPackageType($item['unit'] ?? 'EA'),
                'description' => $item['description'] ?? '',
                'net_weight' => $weight,
                'quantity' => $qty,
                'units' => 'UNIT',
                'fob_value' => number_format($fobValue, 2, '.', ''),
                'currency' => $item['currency'] ?? 'USD',
                'exchange_rate' => '1.00',
                'freight_code' => 'FRT',
                'freight_amount' => number_format($itemFreight, 2, '.', ''),
                'insurance_code' => 'INS',
                'insurance_amount' => number_format($itemInsurance, 2, '.', ''),
                'cif_value' => number_format($cifValue, 2, '.', ''),
                'tax_type_1' => 'CUD',
                'tax_value_1' => number_format($cifValue, 2, '.', ''),
                'tax_type_2' => 'WHA',
                'tax_value_2' => number_format($fobValue, 2, '.', ''),
            ];
        }

        return $items;
    }

    /**
     * Group declaration items by HS code, consolidating quantities, values, and descriptions.
     * Items sharing the same tariff code become a single declaration line.
     */
    protected function groupItemsByHsCode(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $rawCode = $item['hs_code'] ?? $item['tariff_code'] ?? $item['customs_code'] ?? 'UNKNOWN';
            $hsCode = $this->resolveCapsTariffCode($rawCode);
            if (empty($hsCode) || $hsCode === '0000000') {
                $hsCode = 'UNKNOWN';
            }

            if (!isset($groups[$hsCode])) {
                $groups[$hsCode] = [
                    'hs_code' => $hsCode,
                    'descriptions' => [],
                    'quantity' => 0,
                    'fob_value' => 0,
                    'weight' => 0,
                    'country_of_origin' => $item['country_of_origin'] ?? 'US',
                    'unit' => $item['unit'] ?? 'EA',
                    'currency' => $item['currency'] ?? 'USD',
                    'cpc' => $item['cpc'] ?? 'C400',
                    'item_count' => 0,
                ];
            }

            $qty = floatval($item['quantity'] ?? 1);
            $fob = floatval($item['fob_value'] ?? $item['value'] ?? 0);
            if ($fob <= 0 && !empty($item['unit_price'])) {
                $fob = $qty * floatval($item['unit_price']);
            }

            $groups[$hsCode]['quantity'] += $qty;
            $groups[$hsCode]['fob_value'] += $fob;
            $groups[$hsCode]['weight'] += floatval($item['weight'] ?? 0);
            $groups[$hsCode]['item_count']++;

            $desc = trim($item['description'] ?? '');
            if ($desc !== '' && !in_array($desc, $groups[$hsCode]['descriptions'])) {
                $groups[$hsCode]['descriptions'][] = $desc;
            }
        }

        $result = [];
        foreach ($groups as $hsCode => $group) {
            $description = $this->summarizeDescriptions($group['descriptions']);

            $result[] = [
                'hs_code' => $group['hs_code'],
                'description' => $description,
                'quantity' => $group['quantity'],
                'fob_value' => $group['fob_value'],
                'weight' => $group['weight'] > 0 ? $group['weight'] : null,
                'country_of_origin' => $group['country_of_origin'],
                'unit' => $group['unit'],
                'currency' => $group['currency'],
                'cpc' => $group['cpc'],
            ];
        }

        return $result;
    }

    /**
     * Resolve an HS code to a valid 7-digit BVI tariff code that CAPS accepts.
     *
     * Strategy:
     * 1. If already 7 digits after stripping, check it exists, else find nearest match
     * 2. If fewer digits, try finding a valid 7-digit descendant in customs_codes
     * 3. Prefer catch-all "Other" codes (ending in 9) when multiple matches exist
     * 4. If subheading doesn't exist in DB, fall back to "Other" (x.90) under same heading
     * 5. Fall back to zero-padding if nothing else found
     */
    /**
     * HS codes that were split/restructured in HS2017/2022.
     * Maps old 6-digit codes to the correct "Other" catch-all 7-digit code.
     * CAPS BVI uses the latest HS version where these subheadings no longer exist.
     */
    /**
     * Codes that CAPS rejects with trailing "0". Maps 6-digit code to the
     * correct 7-digit CAPS code. When CAPS uses BVI-specific 7th digit "1"
     * instead of "0" for these subheadings.
     */
    protected static array $hsVersionMappings = [
        '200980' => '2009801', // 2009.80 Juice of other single fruit — BVI sub-item 1
        '120790' => '1207901', // 1207.90 Other oil seeds — BVI sub-item 1
        '120700' => '1207901', // 12.07 heading → 1207.901
        '210220' => '2102201', // 2102.20 Inactive yeasts — BVI sub-item 1
    ];

    protected function resolveCapsTariffCode(string $rawCode): string
    {
        $digits = preg_replace('/[^0-9]/', '', $rawCode);
        if (empty($digits)) {
            return '0000000';
        }

        // Check HS version mappings for codes that were split/restructured
        $sixDigit = str_pad($digits, 6, '0');
        if (isset(static::$hsVersionMappings[$sixDigit])) {
            return static::$hsVersionMappings[$sixDigit];
        }

        // If already 7+ digits, check it exists; if so, use it
        if (strlen($digits) >= 7) {
            $digits = substr($digits, 0, 7);
            if ($this->tariffExistsInDb($digits)) {
                return $digits;
            }
            $best = $this->findBest7DigitCode(substr($digits, 0, 6));
            return $best ?: $digits;
        }

        $padded = str_pad($digits, 7, '0');

        if ($this->tariffExistsInDb($padded)) {
            return $padded;
        }

        // Search for 7-digit descendants under the exact prefix first
        // (e.g., 170310 → finds 1703.102)
        $best = $this->findBest7DigitCode($digits);
        if ($best) {
            return $best;
        }

        // If this is a valid 6-digit subheading with no 7-digit children,
        // the padded form (+ trailing 0) is accepted by CAPS.
        $sixDigit = str_pad($digits, 6, '0');
        if (strlen($digits) >= 5 && $this->subheadingExistsInDb($sixDigit)) {
            return str_pad($sixDigit, 7, '0');
        }

        // Subheading doesn't exist in DB — broaden search to the 4-digit heading
        // to find any valid 7-digit descendant (e.g., 1904.20 → 1904.900)
        $heading4 = substr($digits, 0, 4);
        $best = $this->findBest7DigitCode($heading4);
        if ($best) {
            return $best;
        }

        // Last resort: find the "Other" (.90) subheading under the same heading
        $otherCode = $this->findOtherSubheading($heading4);
        if ($otherCode) {
            return $otherCode;
        }

        return $padded;
    }

    /**
     * Public wrapper for resolveCapsTariffCode, used by CapsErrorRecoveryService.
     */
    public function resolveCapsTariffCodePublic(string $rawCode): string
    {
        return $this->resolveCapsTariffCode($rawCode);
    }

    /**
     * Check if a 6-digit subheading exists in the customs_codes table.
     */
    protected function subheadingExistsInDb(string $sixDigits): bool
    {
        $dotted = substr($sixDigits, 0, 4) . '.' . substr($sixDigits, 4);
        return \App\Models\CustomsCode::where('code', $dotted)->exists()
            || \App\Models\CustomsCode::where('code', $sixDigits)->exists();
    }

    /**
     * Find the "Other" (.90) catch-all subheading under a 4-digit heading,
     * returning it as a 7-digit code. Falls back to the last subheading.
     */
    protected function findOtherSubheading(string $heading4): ?string
    {
        $dotted = substr($heading4, 0, 4) . '.';
        $subheadings = \App\Models\CustomsCode::where('code', 'LIKE', $dotted . '%')
            ->get(['code'])
            ->map(function ($c) {
                $digits = preg_replace('/[^0-9]/', '', $c->code);
                return strlen($digits) === 6 ? $digits : null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($subheadings->isEmpty()) {
            return null;
        }

        // Prefer ".90" (Other), then the last subheading (usually the highest/catch-all)
        $other90 = $subheadings->first(fn($c) => str_ends_with($c, '90'));
        if ($other90) {
            return $other90 . '0';
        }

        return $subheadings->last() . '0';
    }

    /**
     * Check if a 7-digit tariff code exists in the customs_codes table (stored with dots).
     */
    protected function tariffExistsInDb(string $sevenDigits): bool
    {
        // BVI codes are stored as "XXXX.XXX" format — insert dot after position 4
        $dotted = substr($sevenDigits, 0, 4) . '.' . substr($sevenDigits, 4);

        return \App\Models\CustomsCode::where('code', $dotted)->exists()
            || \App\Models\CustomsCode::where('code', $sevenDigits)->exists();
    }

    /**
     * Find the best 7-digit tariff code in the DB that starts with the given prefix.
     * Prefers codes ending in 9 ("Other" catch-alls), then 0, then the last available code.
     */
    protected function findBest7DigitCode(string $prefix): ?string
    {
        // Search customs_codes for 7-digit codes starting with this prefix
        // Codes in DB use dots (e.g., "0904.209"), so we need to search with dot format
        $dottedPrefix = strlen($prefix) >= 4
            ? substr($prefix, 0, 4) . '.' . substr($prefix, 4)
            : $prefix;

        $candidates = \App\Models\CustomsCode::where(function ($q) use ($dottedPrefix, $prefix) {
                $q->where('code', 'LIKE', $dottedPrefix . '%')
                  ->orWhere('code', 'LIKE', $prefix . '%');
            })
            ->get(['code'])
            ->map(function ($c) {
                $digits = preg_replace('/[^0-9]/', '', $c->code);
                return strlen($digits) === 7 ? $digits : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Prefer "Other" catch-all: ending in 9, then 0, then last available
        $endingIn9 = $candidates->filter(fn($c) => str_ends_with($c, '9'))->first();
        if ($endingIn9) return $endingIn9;

        $endingIn0 = $candidates->filter(fn($c) => str_ends_with($c, '0'))->first();
        if ($endingIn0) return $endingIn0;

        return $candidates->last();
    }

    /**
     * Build a summarized description from multiple item descriptions.
     * If all descriptions are unique, joins them. If too long, truncates.
     */
    protected function summarizeDescriptions(array $descriptions): string
    {
        if (empty($descriptions)) {
            return '';
        }
        if (count($descriptions) === 1) {
            return $descriptions[0];
        }

        $joined = implode('; ', $descriptions);

        // CAPS description field has a practical limit — truncate if needed
        if (strlen($joined) > 250) {
            $first = $descriptions[0];
            $remaining = count($descriptions) - 1;
            $joined = $first . " (+{$remaining} more items)";
        }

        return $joined;
    }

    /**
     * Map a value to a CAPS reference code using the country_reference_data table.
     * Falls back to hardcoded mappings if the database has no match.
     */
    protected function mapToReference(string $type, ?string $raw, string $default = ''): string
    {
        if (!$raw) return $default;

        $match = \App\Models\CountryReferenceData::where('country_id', 1)
            ->where('reference_type', $type)
            ->where('is_active', true)
            ->where(function ($q) use ($raw) {
                $q->whereRaw('LOWER(code) = ?', [strtolower($raw)])
                  ->orWhereRaw('LOWER(label) LIKE ?', ['%' . strtolower($raw) . '%']);
            })
            ->first();

        return $match?->code ?? $default;
    }

    protected function mapCarrier(?string $carrier): string
    {
        if (!$carrier) return '';
        $code = $this->mapToReference('carrier', $carrier);
        if ($code) return $code;

        $hardcoded = [
            'tropical' => 'TRP', 'seaboard' => 'SMC', 'crowley' => 'CMT',
            'american' => 'AA', 'fedex' => 'FX', 'ups' => 'UPS', 'dhl' => 'DHL',
        ];
        $lower = strtolower($carrier);
        foreach ($hardcoded as $key => $c) {
            if (str_contains($lower, $key)) return $c;
        }
        return strtoupper(substr($carrier, 0, 3));
    }

    protected function mapPort(?string $port): string
    {
        if (!$port) return 'PP';
        $code = $this->mapToReference('port', $port, 'PP');
        return $code ?: 'PP';
    }

    protected function mapPackageType(?string $type): string
    {
        if (!$type) return 'CTN';
        $code = $this->mapToReference('unit', $type);
        if ($code) return $code;

        $hardcoded = [
            'carton' => 'CTN', 'ctn' => 'CTN', 'box' => 'BOX', 'pallet' => 'PLT',
            'bag' => 'BAG', 'case' => 'CS', 'each' => 'EA', 'piece' => 'PCS',
        ];
        $lower = strtolower($type);
        foreach ($hardcoded as $key => $c) {
            if (str_contains($lower, $key)) return $c;
        }
        return strtoupper(substr($type, 0, 3));
    }

    protected function mapUnit(?string $unit): string
    {
        if (!$unit) return 'EA';
        $code = $this->mapToReference('unit', $unit, 'EA');
        return $code ?: 'EA';
    }

    protected function mapCountry(?string $country): string
    {
        if (!$country) return 'US';
        $code = $this->mapToReference('country', $country, 'US');
        return $code ?: 'US';
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
        $headerJson = json_encode($headerData, JSON_PRETTY_PRINT);
        $itemsJson = json_encode($items, JSON_PRETTY_PRINT);
        $missingList = implode("\n- ", $missingFields ?: ['Review all fields']);

        $prompt = <<<PROMPT
You are helping map a customs declaration to the BVI CAPS system fields.

**Available Declaration Data:**
```json
{$declarationData}
```

**Current Header Mapping (some fields may be empty):**
```json
{$headerJson}
```

**Current Items (may need enhancement):**
```json
{$itemsJson}
```

**Missing/Empty Fields to Fill:**
- {$missingList}

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
{"headerData": {}, "items": [], "ai_notes": "explanation"}
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
                'freight_cost' => $declaration->shipment->freight_total ?? $declaration->shipment->freight_cost,
                'insurance_cost' => $declaration->shipment->insurance_total ?? $declaration->shipment->insurance_cost,
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
