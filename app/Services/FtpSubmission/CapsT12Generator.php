<?php

namespace App\Services\FtpSubmission;

use App\Models\CountryReferenceData;
use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Generate CAPS T12 format files for FTP submission
 * 
 * Based on the CAPS Electronic Submission Guide specification:
 * - R10: Header record
 * - R25: Container record (optional)
 * - R26: Header Additional Info (optional)
 * - R30: Item/Record
 * - R40: Charges & Deductions per item
 * - R50: Tax per item
 * - R60: Item Additional Info (optional)
 * - R70: Trailer
 */
class CapsT12Generator
{
    /**
     * Line ending for T12 files (CR+LF as per spec)
     */
    protected const LINE_ENDING = "\r\n";

    /**
     * Country ID for reference data lookups
     */
    protected ?int $countryId = null;

    /**
     * Cache of reference data lookups to avoid repeated DB queries
     */
    protected array $refCache = [];

    /**
     * Generate a T12 file content from a declaration
     */
    public function generate(DeclarationForm $declaration, OrganizationSubmissionCredential $credentials): array
    {
        $declaration->load([
            'country',
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
            'declarationItems' => fn($q) => $q->withoutGlobalScopes(),
            'invoice.invoiceItems' => fn($q) => $q->withoutGlobalScopes(),
            'organization',
        ]);

        // Set country ID for reference data lookups and reset cache
        $this->countryId = $declaration->country_id;
        $this->refCache = [];

        $ftpCreds = $credentials->getFtpCredentials();
        $traderId = $ftpCreds['trader_id'] ?? '';

        if (empty($traderId)) {
            throw new \InvalidArgumentException('Trader ID is required for T12 generation');
        }

        $lines = [];
        $lineCount = 0;

        // R10 - Header
        $headerLine = $this->generateHeader($declaration, $traderId, $ftpCreds);
        $lines[] = $headerLine;
        $lineCount++;

        // R25 - Containers (if applicable)
        $containerLines = $this->generateContainers($declaration);
        foreach ($containerLines as $line) {
            $lines[] = $line;
            $lineCount++;
        }

        // R26 - Header Additional Info (optional)
        $headerInfoLines = $this->generateHeaderAdditionalInfo($declaration);
        foreach ($headerInfoLines as $line) {
            $lines[] = $line;
            $lineCount++;
        }

        // Process items
        $items = $this->getDeclarationItems($declaration);
        foreach ($items as $index => $item) {
            // R30 - Item Record
            $itemLine = $this->generateItemRecord($item, $declaration);
            $lines[] = $itemLine;
            $lineCount++;

            // R40 - Charges & Deductions for this item
            $chargeLines = $this->generateItemCharges($item, $declaration);
            foreach ($chargeLines as $line) {
                $lines[] = $line;
                $lineCount++;
            }

            // R50 - Tax records for this item
            $taxLines = $this->generateItemTaxes($item, $declaration);
            foreach ($taxLines as $line) {
                $lines[] = $line;
                $lineCount++;
            }

            // R60 - Item Additional Info (optional)
            $itemInfoLines = $this->generateItemAdditionalInfo($item);
            foreach ($itemInfoLines as $line) {
                $lines[] = $line;
                $lineCount++;
            }
        }

        // R70 - Trailer (includes the trailer line itself)
        $trailerLine = $this->generateTrailer($lineCount + 1);
        $lines[] = $trailerLine;

        $content = implode(self::LINE_ENDING, $lines);
        $filename = $this->generateFilename($traderId, $declaration);

        return [
            'content' => $content,
            'filename' => $filename,
            'trader_id' => $traderId,
            'line_count' => $lineCount + 1,
            'item_count' => count($items),
        ];
    }

    /**
     * Generate the filename following CAPS convention
     * Format: [NNNNNN][DDMMYYYY].[XXX]
     * Where NNNNNN = 6-digit Trader ID, DDMMYYYY = date, XXX = sequence (001-999)
     */
    public function generateFilename(string $traderId, DeclarationForm $declaration, int $sequence = 1): string
    {
        $paddedTraderId = str_pad(substr($traderId, 0, 6), 6, '0', STR_PAD_LEFT);
        $date = Carbon::parse($declaration->declaration_date ?? now())->format('dmY');
        $paddedSequence = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        return "{$paddedTraderId}{$date}.{$paddedSequence}";
    }

    /**
     * Generate amendment filename (adds 'A' after date)
     */
    public function generateAmendmentFilename(string $traderId, DeclarationForm $declaration, int $sequence = 1): string
    {
        $paddedTraderId = str_pad(substr($traderId, 0, 6), 6, '0', STR_PAD_LEFT);
        $date = Carbon::parse($declaration->declaration_date ?? now())->format('dmY');
        $paddedSequence = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        return "{$paddedTraderId}{$date}A.{$paddedSequence}";
    }

    /**
     * R10 - Header Record
     */
    protected function generateHeader(DeclarationForm $declaration, string $traderId, array $ftpCreds): string
    {
        $shipper = $declaration->shipperContact ?? $declaration->shipment?->shipperContact;
        $importer = $declaration->consigneeContact ?? $declaration->shipment?->consigneeContact;

        $fields = [
            'R10',                                                          // Record Type (mandatory)
            $this->formatField($traderId, 6),                              // Supplier/Trader ID (6 chars)
            $this->formatField($shipper?->company_name ?? $shipper?->name, 40), // Supplier Name
            $this->formatField($shipper?->address_line_1, 30),             // Supplier Address 1
            $this->formatField($shipper?->address_line_2 ?? $shipper?->city, 40), // Supplier Address 2
            $this->formatField($shipper?->postal_code, 9),                 // Supplier Post Code
            $this->formatCountryCode($shipper?->country_code ?? $declaration->country_of_origin), // Supplier Country (2 chars)
            $this->formatField($importer?->trader_id ?? $traderId, 6),     // Importer ID
            $this->formatField($importer?->company_name ?? $importer?->name, 40), // Importer Name
            $this->formatField($importer?->address_line_1, 30),            // Importer Address 1
            $this->formatField($importer?->address_line_2 ?? $importer?->city, 40), // Importer Address 2
            $this->formatField($importer?->postal_code, 9),                // Importer Post Code
            $this->mapCarrierCode($declaration->carrier_name),                 // Carrier ID (3 char code)
            $this->formatField($declaration->vessel_name, 10),             // Carrier No. (vessel name/number)
            $this->mapPortCode($declaration->port_of_arrival),             // Port of Arrival (CAPS port code)
            $this->formatDate($declaration->arrival_date),                 // Arrival Date (DD/MM/YYYY)
            $this->formatField($declaration->manifest_number, 20),         // Manifest No.
            $this->formatNumber($declaration->total_packages ?? 1, 6),     // No. of Packages
            $this->formatField($declaration->bill_of_lading_number ?? $declaration->awb_number, 20), // Bill of Lading
            $this->formatField($shipper?->city ?? '', 20),                 // City (Direct Shipment)
            $this->formatCountryCode($shipper?->country_code ?? $declaration->country_of_origin), // Country (Direct Shipment)
            $this->formatCountryCode($declaration->country_of_origin),     // Country (Original Shipment)
            $this->formatNumber($this->getItemCount($declaration), 3),     // Total No. of Records
            $this->formatDecimal($declaration->freight_total ?? 0, 11, 2), // Total Freight
            $declaration->freight_prorated ? 'Y' : 'N',                    // Is Freight Prorated?
            $this->formatDecimal($declaration->insurance_total ?? 0, 11, 2), // Total Insurance
            $declaration->insurance_prorated ? 'Y' : 'N',                  // Is Insurance Prorated?
            $this->formatDecimal($declaration->total_duty ?? 0, 11, 2),    // Total Payable
            $this->mapPaymentMethod($declaration->payment_method),           // Payment method code (from reference data)
            $this->formatField($ftpCreds['email'] ?? '', 30),              // Declarant Person Name
            $this->formatField($declaration->organization?->trader_id ?? $traderId, 6), // Declarant Company ID
            $this->formatDate($declaration->declaration_date ?? now()),    // Declarant Date
            $this->formatField('Agent', 20),                               // Declarant Role
            $this->formatField($declaration->form_number ?? '', 40),       // Trader Reference
            '',                                                             // Related T12 No. (for amendments)
            $this->getT12Type($declaration),                               // T12 Type (I=Import, D=Deposit, A=Adjustment)
        ];

        return implode(',', $fields);
    }

    /**
     * R25 - Container Records
     */
    protected function generateContainers(DeclarationForm $declaration): array
    {
        $lines = [];
        
        // Check if declaration has container info (stored in items or shipment)
        $containers = $this->extractContainers($declaration);
        
        foreach ($containers as $container) {
            $fields = [
                'R25',                                          // Record Type
                $this->formatField($container['id'], 20),       // Container ID
                $this->formatNumber($container['length'] ?? 0, 4), // Container Length (feet)
            ];
            $lines[] = implode(',', $fields);
        }

        return $lines;
    }

    /**
     * R26 - Header Additional Information
     */
    protected function generateHeaderAdditionalInfo(DeclarationForm $declaration): array
    {
        $lines = [];
        
        // Add any header-level additional info
        // Example: Agricultural certificates, special permissions, etc.
        $additionalInfo = $this->extractHeaderAdditionalInfo($declaration);
        
        foreach ($additionalInfo as $info) {
            $fields = [
                'R26',                                          // Record Type
                $this->formatField($info['code'], 3),           // Additional Info Code
                $this->formatField($info['text'], 70),          // Additional Info Text
            ];
            $lines[] = implode(',', $fields);
        }

        return $lines;
    }

    /**
     * R30 - Item Record
     */
    protected function generateItemRecord(array $item, DeclarationForm $declaration): string
    {
        $fields = [
            'R30',                                                  // Record Type
            $this->mapCpcCode($declaration->cpc_code),                 // CPC Code (4 chars, from reference data)
            $this->formatTariffNumber($item['tariff_number'] ?? ''), // Tariff Number (7 digits, no periods)
            $this->formatCountryCode($item['country_of_origin'] ?? $declaration->country_of_origin), // Country of Origin
            $this->formatNumber($item['packages'] ?? 1, 6),        // No. of Packages
            $this->mapPackageType($item['package_type'] ?? ''),    // Type of Packages (CAPS code)
            $this->formatField($item['description'] ?? '', 200),   // Description
            $this->formatDecimal($item['net_weight'] ?? 0, 11, 2), // Net Weight
            $this->formatDecimal($item['quantity'] ?? 1, 11, 2),   // Quantity
            $this->mapUnitCode($item['units'] ?? ''),              // Units (CAPS unit code)
            $this->formatDecimal($item['fob_value'] ?? 0, 11, 2),  // FOB Value
            $this->formatDecimal($item['cif_value'] ?? $item['fob_value'] ?? 0, 11, 2), // CIF Value
            $this->formatDecimal($item['total_due'] ?? 0, 11, 2),  // Total Due
        ];

        return implode(',', $fields);
    }

    /**
     * R40 - Charges and Deductions per item
     */
    protected function generateItemCharges(array $item, DeclarationForm $declaration): array
    {
        $lines = [];
        
        // Freight charge
        if (!empty($item['freight_amount']) && $item['freight_amount'] > 0) {
            $fields = [
                'R40',                                              // Record Type
                $this->mapChargeCode('FRT'),                        // Charge/Deduction Code (Freight)
                $this->formatDecimal($item['freight_amount'], 11, 2), // Amount
            ];
            $lines[] = implode(',', $fields);
        }

        // Insurance charge
        if (!empty($item['insurance_amount']) && $item['insurance_amount'] > 0) {
            $fields = [
                'R40',                                              // Record Type
                $this->mapChargeCode('INS'),                        // Charge/Deduction Code (Insurance)
                $this->formatDecimal($item['insurance_amount'], 11, 2), // Amount
            ];
            $lines[] = implode(',', $fields);
        }

        return $lines;
    }

    /**
     * R50 - Tax Records per item
     */
    protected function generateItemTaxes(array $item, DeclarationForm $declaration): array
    {
        $lines = [];
        
        // Custom Duty
        if (!empty($item['customs_duty']) && $item['customs_duty'] > 0) {
            $fields = [
                'R50',                                              // Record Type
                $this->mapTaxType('CUD'),                           // Tax Type (Custom Duty, from reference data)
                '',                                                 // Exemption Indicator (E if exempt)
                $this->formatDecimal($item['cif_value'] ?? 0, 11, 2), // Value for Tax
                $this->formatDecimal($item['duty_rate'] ?? 0, 7, 3), // Tax Rate
                $this->formatDecimal($item['customs_duty'], 11, 2), // Tax Amount
            ];
            $lines[] = implode(',', $fields);
        }

        // Wharfage
        if (!empty($item['wharfage']) && $item['wharfage'] > 0) {
            $fields = [
                'R50',                                              // Record Type
                $this->mapTaxType('WHA'),                           // Tax Type (Wharfage, from reference data)
                '',                                                 // Exemption Indicator
                $this->formatDecimal($item['cif_value'] ?? 0, 11, 2), // Value for Tax
                $this->formatDecimal(1.00, 7, 3),                   // Tax Rate (usually 1%)
                $this->formatDecimal($item['wharfage'], 11, 2),     // Tax Amount
            ];
            $lines[] = implode(',', $fields);
        }

        // Other levies from declaration
        if (!empty($item['other_levies'])) {
            foreach ($item['other_levies'] as $levy) {
                $fields = [
                    'R50',
                    $this->mapTaxType($levy['code'] ?? 'OTH'),
                    !empty($levy['exempt']) ? 'E' : '',
                    $this->formatDecimal($levy['value'] ?? 0, 11, 2),
                    $this->formatDecimal($levy['rate'] ?? 0, 7, 3),
                    $this->formatDecimal($levy['amount'] ?? 0, 11, 2),
                ];
                $lines[] = implode(',', $fields);
            }
        }

        return $lines;
    }

    /**
     * R60 - Item Additional Information
     */
    protected function generateItemAdditionalInfo(array $item): array
    {
        $lines = [];
        
        if (!empty($item['additional_info'])) {
            foreach ($item['additional_info'] as $info) {
                $fields = [
                    'R60',                                          // Record Type
                    $this->formatField($info['code'] ?? 'GEN', 3),  // Additional Info Code
                    $this->formatField($info['text'] ?? '', 70),    // Additional Info Text
                ];
                $lines[] = implode(',', $fields);
            }
        }

        return $lines;
    }

    /**
     * R70 - Trailer Record
     */
    protected function generateTrailer(int $totalLines): string
    {
        $fields = [
            'R70',                                   // Record Type
            $this->formatNumber($totalLines, 5),    // Total No. Lines in File (including trailer)
        ];

        return implode(',', $fields);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Get declaration items in a normalized format
     */
    /**
     * Look up duty data for an item, trying the exact description first,
     * then the composite key (description|fob_value) for duplicates.
     */
    protected function findDutyData(array $dutyLookup, string $description, float $fobValue = 0): array
    {
        if (isset($dutyLookup[$description])) {
            return $dutyLookup[$description];
        }
        // Try composite key used when duplicate descriptions exist
        $compositeKey = $description . '|' . $fobValue;
        if (isset($dutyLookup[$compositeKey])) {
            return $dutyLookup[$compositeKey];
        }
        return [];
    }

    protected function getDeclarationItems(DeclarationForm $declaration): array
    {
        $items = [];
        
        // Build lookup map from duty_breakdown for duty/tax data by description
        $dutyLookup = $this->buildDutyLookup($declaration);

        // First try declaration items
        if ($declaration->declarationItems && $declaration->declarationItems->count() > 0) {
            foreach ($declaration->declarationItems as $declItem) {
                $dutyData = $this->findDutyData($dutyLookup, $declItem->description, (float) ($declItem->line_total ?? 0));
                
                $items[] = [
                    'tariff_number' => $declItem->hs_code ?? $dutyData['tariff_code'] ?? '',
                    'description' => $declItem->description,
                    'quantity' => $declItem->quantity ?? $dutyData['quantity'] ?? 1,
                    'units' => $declItem->unit_of_measure ?? 'EA',
                    'fob_value' => $dutyData['fob_value'] ?? $declItem->line_total ?? 0,
                    'cif_value' => $dutyData['cif_value'] ?? $declItem->line_total ?? 0,
                    'customs_duty' => $dutyData['customs_duty'] ?? 0,
                    'duty_rate' => $dutyData['duty_rate'] ?? 0,
                    'wharfage' => $dutyData['wharfage'] ?? 0,
                    'net_weight' => $dutyData['net_weight'] ?? 0,
                    'packages' => $dutyData['packages'] ?? 1,
                    'package_type' => $dutyData['package_type'] ?? '',
                    'country_of_origin' => $dutyData['country_of_origin'] ?? $declaration->country_of_origin,
                    'freight_amount' => $dutyData['freight_amount'] ?? 0,
                    'insurance_amount' => $dutyData['insurance_amount'] ?? 0,
                    'total_due' => $dutyData['total_due'] ?? 0,
                    'other_levies' => $dutyData['other_levies'] ?? [],
                    'additional_info' => [],
                ];
            }
        }
        // Fall back to invoice items
        elseif ($declaration->invoice && $declaration->invoice->invoiceItems) {
            foreach ($declaration->invoice->invoiceItems as $invoiceItem) {
                $itemFob = (float) ($invoiceItem->total_value ?? ($invoiceItem->unit_price * ($invoiceItem->quantity ?? 1)));
                $dutyData = $this->findDutyData($dutyLookup, $invoiceItem->description, $itemFob);
                
                $items[] = [
                    'tariff_number' => $invoiceItem->classified_hs_code ?? $invoiceItem->hs_code ?? $dutyData['tariff_code'] ?? '',
                    'description' => $invoiceItem->description,
                    'quantity' => $invoiceItem->quantity ?? $dutyData['quantity'] ?? 1,
                    'units' => $invoiceItem->unit_of_measure ?? 'EA',
                    'fob_value' => $dutyData['fob_value'] ?? $itemFob,
                    'cif_value' => $dutyData['cif_value'] ?? $invoiceItem->total_value ?? 0,
                    'customs_duty' => $dutyData['customs_duty'] ?? 0,
                    'duty_rate' => $dutyData['duty_rate'] ?? 0,
                    'wharfage' => $dutyData['wharfage'] ?? 0,
                    'net_weight' => $invoiceItem->weight ?? 0,
                    'packages' => 1,
                    'package_type' => '',
                    'country_of_origin' => $invoiceItem->country_of_origin ?? $declaration->country_of_origin,
                    'freight_amount' => $dutyData['freight_amount'] ?? 0,
                    'insurance_amount' => $dutyData['insurance_amount'] ?? 0,
                    'total_due' => $dutyData['total_due'] ?? 0,
                    'other_levies' => [],
                    'additional_info' => [],
                ];
            }
        }
        // Fall back to items JSON field
        elseif (!empty($declaration->items) && is_array($declaration->items)) {
            foreach ($declaration->items as $item) {
                $dutyData = $this->findDutyData($dutyLookup, $item['description'] ?? '', (float) ($item['fob_value'] ?? $item['value'] ?? 0));
                
                $items[] = [
                    'tariff_number' => $item['tariff_code'] ?? $item['hs_code'] ?? $dutyData['tariff_code'] ?? '',
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? $dutyData['quantity'] ?? 1,
                    'units' => $item['units'] ?? 'EA',
                    'fob_value' => $dutyData['fob_value'] ?? $item['fob_value'] ?? $item['value'] ?? 0,
                    'cif_value' => $dutyData['cif_value'] ?? $item['cif_value'] ?? $item['fob_value'] ?? $item['value'] ?? 0,
                    'customs_duty' => $dutyData['customs_duty'] ?? $item['customs_duty'] ?? 0,
                    'duty_rate' => $dutyData['duty_rate'] ?? $item['duty_rate'] ?? 0,
                    'wharfage' => $dutyData['wharfage'] ?? $item['wharfage'] ?? 0,
                    'net_weight' => $item['net_weight'] ?? 0,
                    'packages' => $item['packages'] ?? 1,
                    'package_type' => $item['package_type'] ?? '',
                    'country_of_origin' => $item['country_of_origin'] ?? $declaration->country_of_origin,
                    'freight_amount' => $dutyData['freight_amount'] ?? $item['freight'] ?? 0,
                    'insurance_amount' => $dutyData['insurance_amount'] ?? $item['insurance'] ?? 0,
                    'total_due' => $dutyData['total_due'] ?? $item['total_duty'] ?? 0,
                    'other_levies' => $item['other_levies'] ?? [],
                    'additional_info' => [],
                ];
            }
        }

        return $items;
    }
    
    /**
     * Build a lookup map of duty data by item description from duty_breakdown
     */
    protected function buildDutyLookup(DeclarationForm $declaration): array
    {
        $lookup = [];
        
        if (empty($declaration->duty_breakdown) || !is_array($declaration->duty_breakdown)) {
            return $lookup;
        }
        
        // Calculate wharfage rate (typically 1% of CIF in BVI)
        $wharfageRate = 0.01;
        
        foreach ($declaration->duty_breakdown as $tariffGroup) {
            $tariffCode = $tariffGroup['tariff_code'] ?? '';
            $dutyRate = $tariffGroup['duty_rate'] ?? 0;
            $itemCount = $tariffGroup['item_count'] ?? count($tariffGroup['items'] ?? []);
            
            // Per-item values for freight/insurance (prorated within tariff group)
            $groupTotalFob = $tariffGroup['total_fob'] ?? 0;
            $groupTotalFreight = $tariffGroup['total_freight'] ?? 0;
            $groupTotalInsurance = $tariffGroup['total_insurance'] ?? 0;
            $groupTotalDuty = $tariffGroup['total_duty'] ?? 0;
            
            if (!empty($tariffGroup['items'])) {
                foreach ($tariffGroup['items'] as $item) {
                    $description = $item['description'] ?? '';
                    $fobValue = $item['fob_value'] ?? 0;
                    $cifValue = $item['cif_value'] ?? $fobValue;
                    
                    // Calculate prorated freight/insurance based on FOB proportion
                    $fobProportion = $groupTotalFob > 0 ? ($fobValue / $groupTotalFob) : (1 / max(1, $itemCount));
                    $itemFreight = $groupTotalFreight * $fobProportion;
                    $itemInsurance = $groupTotalInsurance * $fobProportion;
                    
                    // Calculate item duty based on CIF and rate
                    $itemDuty = $cifValue * ($dutyRate / 100);
                    
                    // Calculate wharfage
                    $itemWharfage = $cifValue * $wharfageRate;
                    
                    $dutyEntry = [
                        'tariff_code' => $tariffCode,
                        'duty_rate' => $dutyRate,
                        'quantity' => $item['quantity'] ?? 1,
                        'fob_value' => $fobValue,
                        'cif_value' => $cifValue,
                        'customs_duty' => round($itemDuty, 2),
                        'wharfage' => round($itemWharfage, 2),
                        'freight_amount' => round($itemFreight, 2),
                        'insurance_amount' => round($itemInsurance, 2),
                        'total_due' => round($itemDuty + $itemWharfage, 2),
                        'net_weight' => 0,
                        'packages' => 1,
                        'package_type' => '',
                        'country_of_origin' => '',
                        'other_levies' => [],
                    ];
                    
                    // Use description as primary key; on collision, add fob_value to disambiguate
                    $key = $description;
                    if (isset($lookup[$key])) {
                        $key = $description . '|' . $fobValue;
                    }
                    $lookup[$key] = $dutyEntry;
                }
            }
        }
        
        return $lookup;
    }

    /**
     * Get item count
     */
    protected function getItemCount(DeclarationForm $declaration): int
    {
        return count($this->getDeclarationItems($declaration));
    }

    /**
     * Extract container information from declaration
     */
    protected function extractContainers(DeclarationForm $declaration): array
    {
        // Check if shipment has container info
        if ($declaration->shipment && !empty($declaration->shipment->containers)) {
            return $declaration->shipment->containers;
        }

        return [];
    }

    /**
     * Extract header additional information
     */
    protected function extractHeaderAdditionalInfo(DeclarationForm $declaration): array
    {
        $info = [];

        // Add agricultural certificate if present
        if (!empty($declaration->agricultural_certificate)) {
            $info[] = [
                'code' => 'AGR',
                'text' => 'Agricultural certificate ' . $declaration->agricultural_certificate,
            ];
        }

        return $info;
    }

    /**
     * Get T12 type
     */
    protected function getT12Type(DeclarationForm $declaration): string
    {
        // I = Import, D = Deposit, A = Adjustment
        return 'I';
    }

    // ==========================================
    // Code Mapping Helpers (uses CountryReferenceData from DB)
    // ==========================================

    /**
     * Look up a CAPS reference code from the database.
     * Caches results per type+value to avoid repeated queries.
     */
    protected function lookupReferenceCode(string $type, ?string $value, string $fallback = ''): string
    {
        if (empty($value) || !$this->countryId) {
            return $fallback;
        }

        $cacheKey = $type . '|' . strtolower(trim($value));

        if (isset($this->refCache[$cacheKey])) {
            return $this->refCache[$cacheKey];
        }

        $ref = CountryReferenceData::findByLocalMatch($this->countryId, $type, $value);

        $code = $ref ? $ref->code : $fallback;
        $this->refCache[$cacheKey] = $code;

        return $code;
    }

    /**
     * Map port name to CAPS port code via reference data
     */
    protected function mapPortCode(?string $port): string
    {
        return $this->lookupReferenceCode(CountryReferenceData::TYPE_PORT, $port, '');
    }

    /**
     * Map carrier/vessel name to CAPS carrier code via reference data
     */
    protected function mapCarrierCode(?string $carrier): string
    {
        return $this->lookupReferenceCode(CountryReferenceData::TYPE_CARRIER, $carrier, '');
    }

    /**
     * Map unit of measure to CAPS unit code via reference data
     */
    protected function mapUnitCode(?string $unit): string
    {
        return $this->lookupReferenceCode(CountryReferenceData::TYPE_UNIT, $unit, 'EA');
    }

    /**
     * Map CPC code via reference data, default to C400 (Release to Free Circulation)
     */
    protected function mapCpcCode(?string $cpc): string
    {
        if (!empty($cpc)) {
            $code = $this->lookupReferenceCode(CountryReferenceData::TYPE_CPC, $cpc, '');
            if (!empty($code)) {
                return $code;
            }
            // Already a valid short code
            if (strlen(trim($cpc)) <= 4) {
                return strtoupper(trim($cpc));
            }
        }

        // Look up default CPC from reference data
        if ($this->countryId) {
            $default = CountryReferenceData::where('country_id', $this->countryId)
                ->ofType(CountryReferenceData::TYPE_CPC)
                ->default()
                ->first();
            if ($default) {
                return $default->code;
            }
        }

        return 'C400';
    }

    /**
     * Map payment method via reference data
     */
    protected function mapPaymentMethod(?string $method): string
    {
        if (!empty($method)) {
            $code = $this->lookupReferenceCode(CountryReferenceData::TYPE_PAYMENT_METHOD, $method, '');
            if (!empty($code)) {
                return $code;
            }
            if (strlen(trim($method)) <= 3) {
                return strtoupper(trim($method));
            }
        }

        // Look up default payment method from reference data
        if ($this->countryId) {
            $default = CountryReferenceData::where('country_id', $this->countryId)
                ->ofType(CountryReferenceData::TYPE_PAYMENT_METHOD)
                ->default()
                ->first();
            if ($default) {
                return $default->code;
            }
        }

        return '';
    }

    /**
     * Validate/map charge code against reference data
     */
    protected function mapChargeCode(?string $code): string
    {
        if (empty($code)) {
            return 'OTH';
        }
        $mapped = $this->lookupReferenceCode(CountryReferenceData::TYPE_CHARGE_CODE, $code, '');
        return !empty($mapped) ? $mapped : strtoupper(trim($code));
    }

    /**
     * Validate/map tax type against reference data
     */
    protected function mapTaxType(?string $code): string
    {
        if (empty($code)) {
            return '';
        }
        $mapped = $this->lookupReferenceCode(CountryReferenceData::TYPE_TAX_TYPE, $code, '');
        return !empty($mapped) ? $mapped : strtoupper(trim($code));
    }

    /**
     * Map package type -- not a separate reference type, so use a simple fallback
     */
    protected function mapPackageType(?string $type): string
    {
        if (empty($type)) {
            return '';
        }
        // If it's already a short code, return as-is
        if (strlen(trim($type)) <= 3) {
            return strtoupper(trim($type));
        }
        return strtoupper(substr(trim($type), 0, 3));
    }

    // ==========================================
    // Formatting Helpers
    // ==========================================

    /**
     * Format a string field, stripping commas and limiting length
     */
    protected function formatField(?string $value, int $maxLength): string
    {
        if ($value === null) {
            return '';
        }

        // Strip commas (field delimiter)
        $value = str_replace(',', ' ', $value);
        
        // Limit length
        return Str::limit($value, $maxLength, '');
    }

    /**
     * Format a date as DD/MM/YYYY
     */
    protected function formatDate($date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Format a numeric field
     */
    protected function formatNumber($value, int $maxDigits): string
    {
        return (string) min((int) $value, pow(10, $maxDigits) - 1);
    }

    /**
     * Format a decimal number
     */
    protected function formatDecimal($value, int $totalDigits, int $decimalPlaces): string
    {
        $formatted = number_format((float) $value, $decimalPlaces, '.', '');
        return $formatted;
    }

    /**
     * Format country code (2 characters, ISO 3166-1 alpha-2)
     * Uses CountryReferenceData for lookups, with ISO fallback
     */
    protected function formatCountryCode(?string $code): string
    {
        if (empty($code)) {
            return '';
        }

        $code = strtoupper(trim($code));

        // If already a 2-letter code, verify it exists in reference data or return as-is
        if (strlen($code) === 2) {
            return $code;
        }

        // Try database reference data lookup (handles full names and local matches)
        $refCode = $this->lookupReferenceCode(CountryReferenceData::TYPE_COUNTRY, $code, '');
        if (!empty($refCode)) {
            return $refCode;
        }

        // Fallback: common ISO 3166-1 alpha-3 to alpha-2 conversions
        $iso3to2 = [
            'USA' => 'US', 'GBR' => 'GB', 'VGB' => 'VG', 'CHN' => 'CN',
            'JPN' => 'JP', 'DEU' => 'DE', 'FRA' => 'FR', 'ITA' => 'IT',
            'CAN' => 'CA', 'MEX' => 'MX', 'BRA' => 'BR', 'IND' => 'IN',
            'AUS' => 'AU', 'TTO' => 'TT', 'JAM' => 'JM', 'BRB' => 'BB',
        ];

        if (strlen($code) === 3 && isset($iso3to2[$code])) {
            return $iso3to2[$code];
        }

        return substr($code, 0, 2);
    }

    /**
     * Format tariff number (7 digits, no periods)
     */
    protected function formatTariffNumber(?string $tariff): string
    {
        if (empty($tariff)) {
            return '';
        }

        // Remove all non-numeric characters
        $numeric = preg_replace('/[^0-9]/', '', $tariff);
        
        // Pad or trim to 7 digits
        return str_pad(substr($numeric, 0, 7), 7, '0', STR_PAD_RIGHT);
    }

    /**
     * Preview the T12 content (formatted for display)
     */
    public function preview(DeclarationForm $declaration, OrganizationSubmissionCredential $credentials): array
    {
        $result = $this->generate($declaration, $credentials);
        
        // Parse content into structured format for display
        $lines = explode(self::LINE_ENDING, $result['content']);
        $parsedLines = [];
        $recordCounts = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $parts = str_getcsv($line);
            $recordType = $parts[0] ?? '';
            $recordTypeName = $this->getRecordTypeName($recordType);
            
            // Count record types by code (e.g., R10, R30, R40, R50, R70)
            if (!empty($recordType)) {
                $key = $recordType . ' (' . $recordTypeName . ')';
                if (!isset($recordCounts[$key])) {
                    $recordCounts[$key] = 0;
                }
                $recordCounts[$key]++;
            }
            
            $parsedLines[] = [
                'raw' => $line,
                'record_type' => $recordType,
                'record_type_name' => $recordTypeName,
                'fields' => $parts,
            ];
        }
        
        // Sort record counts by record type code
        uksort($recordCounts, function($a, $b) {
            return strcmp($a, $b);
        });

        return [
            'filename' => $result['filename'],
            'trader_id' => $result['trader_id'],
            'line_count' => $result['line_count'],
            'item_count' => $result['item_count'],
            'size' => strlen($result['content']),
            'lines' => $parsedLines,
            'content' => $result['content'],
            'raw_content' => $result['content'],
            'record_counts' => $recordCounts,
            'validation_errors' => [],
        ];
    }

    /**
     * Get human-readable record type name
     */
    protected function getRecordTypeName(string $type): string
    {
        return match ($type) {
            'R10' => 'Header',
            'R25' => 'Container',
            'R26' => 'Header Additional Info',
            'R30' => 'Item/Record',
            'R40' => 'Charges & Deductions',
            'R50' => 'Tax',
            'R60' => 'Item Additional Info',
            'R70' => 'Trailer',
            default => 'Unknown',
        };
    }
}
