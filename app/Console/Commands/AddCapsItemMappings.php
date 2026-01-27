<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class AddCapsItemMappings extends Command
{
    protected $signature = 'caps:add-items';
    protected $description = 'Add per-item field mappings and all lookup values for CAPS';

    public function handle()
    {
        $this->info('Adding CAPS per-item field mappings and lookup values...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $page = WebFormPage::where('web_form_target_id', $target->id)
            ->where('name', 'TD Data Entry')
            ->first();

        if (!$page) {
            $this->error('TD Data Entry page not found.');
            return 1;
        }

        // Add per-item field mappings
        $itemMappingsCount = $this->createItemFieldMappings($page);
        $this->info("Created {$itemMappingsCount} per-item field mappings");

        // Add all dropdown values
        $dropdownCount = $this->createAllDropdownValues($page);
        $this->info("Created {$dropdownCount} dropdown values");

        $this->info('');
        $this->info('CAPS item mappings complete!');
        $this->info("Total Field Mappings: {$page->fieldMappings()->count()}");

        return 0;
    }

    protected function createItemFieldMappings(WebFormPage $page): int
    {
        $mappings = [
            // ============================================
            // SECTION 12: CPC (Customs Procedure Code)
            // ============================================
            [
                'section' => '12. CPC',
                'web_field_label' => 'CPC',
                'web_field_name' => 'rec{N}_CPC',
                'web_field_selectors' => ['input[name*="CPC"]', 'input:near(:text("CPC"))'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => 'C400',
                'is_required' => true,
                'tab_order' => 100,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 13: TARIFF NO.
            // ============================================
            [
                'section' => '13. Tariff',
                'web_field_label' => 'Tariff No.',
                'web_field_name' => 'rec{N}_TariffNo',
                'web_field_selectors' => ['input[name*="TariffNo"]', 'input:near(:text("TARIFF NO"))'],
                'field_type' => 'text',
                'local_field' => 'item.customs_code_formatted',
                'value_transform' => ['type' => 'remove_dots'],
                'is_required' => true,
                'tab_order' => 101,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 14: COUNTRY OF ORIGIN
            // ============================================
            [
                'section' => '14. Origin',
                'web_field_label' => 'Country of Origin (Item)',
                'web_field_name' => 'rec{N}_CountryOfOrigin',
                'web_field_selectors' => ['input[name*="CountryOfOrigin"]', 'input:near(:text("COUNTRY OF ORIGIN"))'],
                'field_type' => 'text',
                'local_field' => 'item.country_of_origin',
                'default_value' => 'US',
                'is_required' => true,
                'tab_order' => 102,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 15: PACKAGES
            // ============================================
            [
                'section' => '15. Packages',
                'web_field_label' => 'No. of Packages',
                'web_field_name' => 'rec{N}_NoOfPackages',
                'web_field_selectors' => ['input[name*="NoOfPackages"]:first', 'input:near(:text("NO. AND TYPE")):first'],
                'field_type' => 'number',
                'local_field' => 'item.quantity',
                'is_required' => true,
                'tab_order' => 103,
                'is_repeatable' => true,
            ],
            [
                'section' => '15. Packages',
                'web_field_label' => 'Type of Packages',
                'web_field_name' => 'rec{N}_TypeOfPackages',
                'web_field_selectors' => ['input[name*="TypeOfPackages"]', 'input:near(:text("NO. AND TYPE")):last'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => 'CTN',
                'is_required' => true,
                'tab_order' => 104,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 16: DESCRIPTION
            // ============================================
            [
                'section' => '16. Description',
                'web_field_label' => 'Description',
                'web_field_name' => 'rec{N}_Description',
                'web_field_selectors' => ['textarea[name*="Description"]', 'input[name*="Description"]', 'input:near(:text("DESCRIPTION"))'],
                'field_type' => 'textarea',
                'local_field' => 'item.description',
                'is_required' => true,
                'tab_order' => 105,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 17: WEIGHT & QUANTITY
            // ============================================
            [
                'section' => '17. Weight',
                'web_field_label' => 'Net Weight (LB)',
                'web_field_name' => 'rec{N}_NetWeight',
                'web_field_selectors' => ['input[name*="NetWeight"]', 'input:near(:text("NET WEIGHT"))'],
                'field_type' => 'number',
                'local_field' => 'item.weight',
                'is_required' => false,
                'tab_order' => 106,
                'is_repeatable' => true,
            ],
            [
                'section' => '17. Quantity',
                'web_field_label' => 'Quantity',
                'web_field_name' => 'rec{N}_Quantity2',
                'web_field_selectors' => ['input[name*="Quantity2"]', 'input:near(:text("QUANTITY")):first'],
                'field_type' => 'number',
                'local_field' => 'item.quantity',
                'is_required' => true,
                'tab_order' => 107,
                'is_repeatable' => true,
            ],
            [
                'section' => '17. Units',
                'web_field_label' => 'Units',
                'web_field_name' => 'rec{N}_UnitForQuantity',
                'web_field_selectors' => ['input[name*="UnitForQuantity"]', 'input:near(:text("UNITS"))'],
                'field_type' => 'text',
                'local_field' => 'item.unit_of_measure',
                'default_value' => 'EA',
                'is_required' => false,
                'tab_order' => 108,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 18: F.O.B. VALUE
            // ============================================
            [
                'section' => '18. FOB',
                'web_field_label' => 'F.O.B. Value',
                'web_field_name' => 'rec{N}_RecordValue',
                'web_field_selectors' => ['input[name*="RecordValue"]', 'input:near(:text("F.O.B. VALUE"))'],
                'field_type' => 'number',
                'local_field' => 'item.fob_value',
                'is_required' => true,
                'tab_order' => 109,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 19: CURRENCY & EXCHANGE
            // ============================================
            [
                'section' => '19. Currency',
                'web_field_label' => 'Currency',
                'web_field_name' => 'rec{N}_CurrencyCode',
                'web_field_selectors' => ['input[name*="CurrencyCode"]', 'input:near(:text("CURRENCY"))'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => 'USD',
                'is_required' => true,
                'tab_order' => 110,
                'is_repeatable' => true,
            ],
            [
                'section' => '19. Exchange',
                'web_field_label' => 'Exchange Rate',
                'web_field_name' => 'rec{N}_ExchangeRate',
                'web_field_selectors' => ['input[name*="ExchangeRate"]', 'input:near(:text("EXCHANGE RATE"))'],
                'field_type' => 'number',
                'local_field' => null,
                'static_value' => '1.00',
                'is_required' => true,
                'tab_order' => 111,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 20: CHARGES / DEDUCTIONS
            // ============================================
            [
                'section' => '20. Charges',
                'web_field_label' => 'Freight Charge Code',
                'web_field_name' => 'rec{N}_ChargeCode_line1',
                'web_field_selectors' => ['input[name*="ChargeCode_line1"]', 'input:near(:text("CHARGES")):first'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => 'FRT',
                'is_required' => true,
                'tab_order' => 112,
                'is_repeatable' => true,
            ],
            [
                'section' => '20. Charges',
                'web_field_label' => 'Freight Amount',
                'web_field_name' => 'rec{N}_ChargeAmount_line1',
                'web_field_selectors' => ['input[name*="ChargeAmount_line1"]'],
                'field_type' => 'number',
                'local_field' => 'item.prorated_freight',
                'is_required' => true,
                'tab_order' => 113,
                'is_repeatable' => true,
            ],
            [
                'section' => '20. Charges',
                'web_field_label' => 'Insurance Charge Code',
                'web_field_name' => 'rec{N}_ChargeCode_line2',
                'web_field_selectors' => ['input[name*="ChargeCode_line2"]'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => 'INS',
                'is_required' => true,
                'tab_order' => 114,
                'is_repeatable' => true,
            ],
            [
                'section' => '20. Charges',
                'web_field_label' => 'Insurance Amount',
                'web_field_name' => 'rec{N}_ChargeAmount_line2',
                'web_field_selectors' => ['input[name*="ChargeAmount_line2"]'],
                'field_type' => 'number',
                'local_field' => 'item.prorated_insurance',
                'is_required' => true,
                'tab_order' => 115,
                'is_repeatable' => true,
            ],

            // ============================================
            // SECTION 22: TAX TYPES
            // ============================================
            [
                'section' => '22. Tax',
                'web_field_label' => 'Tax Type 1 (Customs Duty)',
                'web_field_name' => 'rec{N}_TaxType_line1',
                'web_field_selectors' => ['input[name*="TaxType_line1"]'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => 'CUD',
                'is_required' => true,
                'tab_order' => 120,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Tax Exempt Ind. 1',
                'web_field_name' => 'rec{N}_TaxId_line1',
                'web_field_selectors' => ['input[name*="TaxId_line1"]'],
                'field_type' => 'text',
                'local_field' => null,
                'is_required' => false,
                'tab_order' => 121,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Value for Tax 1 (CIF)',
                'web_field_name' => 'rec{N}_TaxValue_line1',
                'web_field_selectors' => ['input[name*="TaxValue_line1"]'],
                'field_type' => 'number',
                'local_field' => 'item.cif_value',
                'is_required' => true,
                'tab_order' => 122,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Tax Rate 1 (Duty %)',
                'web_field_name' => 'rec{N}_TaxRate_line1',
                'web_field_selectors' => ['input[name*="TaxRate_line1"]'],
                'field_type' => 'number',
                'local_field' => 'item.duty_rate',
                'is_required' => true,
                'tab_order' => 123,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Tax Amount 1 (Duty)',
                'web_field_name' => 'rec{N}_TaxAmount_line1',
                'web_field_selectors' => ['input[name*="TaxAmount_line1"]'],
                'field_type' => 'number',
                'local_field' => 'item.customs_duty',
                'is_required' => true,
                'tab_order' => 124,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Tax Type 2 (Wharfage)',
                'web_field_name' => 'rec{N}_TaxType_line2',
                'web_field_selectors' => ['input[name*="TaxType_line2"]'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => 'WHA',
                'is_required' => true,
                'tab_order' => 125,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Value for Tax 2 (FOB)',
                'web_field_name' => 'rec{N}_TaxValue_line2',
                'web_field_selectors' => ['input[name*="TaxValue_line2"]'],
                'field_type' => 'number',
                'local_field' => 'item.fob_value',
                'is_required' => true,
                'tab_order' => 126,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Tax Rate 2 (Wharfage %)',
                'web_field_name' => 'rec{N}_TaxRate_line2',
                'web_field_selectors' => ['input[name*="TaxRate_line2"]'],
                'field_type' => 'number',
                'local_field' => null,
                'static_value' => '2.00',
                'is_required' => true,
                'tab_order' => 127,
                'is_repeatable' => true,
            ],
            [
                'section' => '22. Tax',
                'web_field_label' => 'Tax Amount 2 (Wharfage)',
                'web_field_name' => 'rec{N}_TaxAmount_line2',
                'web_field_selectors' => ['input[name*="TaxAmount_line2"]'],
                'field_type' => 'number',
                'local_field' => 'item.wharfage',
                'is_required' => true,
                'tab_order' => 128,
                'is_repeatable' => true,
            ],
        ];

        $count = 0;
        foreach ($mappings as $mapping) {
            WebFormFieldMapping::updateOrCreate(
                [
                    'web_form_page_id' => $page->id,
                    'web_field_label' => $mapping['web_field_label'],
                ],
                [
                    'local_field' => $mapping['local_field'] ?? null,
                    'web_field_name' => $mapping['web_field_name'] ?? null,
                    'web_field_selectors' => $mapping['web_field_selectors'],
                    'field_type' => $mapping['field_type'],
                    'static_value' => $mapping['static_value'] ?? null,
                    'default_value' => $mapping['default_value'] ?? null,
                    'value_transform' => $mapping['value_transform'] ?? null,
                    'is_required' => $mapping['is_required'] ?? false,
                    'tab_order' => $mapping['tab_order'] ?? null,
                    'section' => $mapping['section'] ?? null,
                    'is_active' => true,
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function createAllDropdownValues(WebFormPage $page): int
    {
        $dropdowns = [
            // ============================================
            // CPC CODES
            // ============================================
            'CPC' => [
                ['option_value' => 'C400', 'option_label' => 'C400 - Import for Home Consumption', 'local_equivalent' => 'import', 'is_default' => true],
                ['option_value' => 'C410', 'option_label' => 'C410 - Import with Duty Exemption', 'local_equivalent' => 'exempt'],
                ['option_value' => 'C420', 'option_label' => 'C420 - Temporary Import', 'local_equivalent' => 'temporary'],
                ['option_value' => 'C430', 'option_label' => 'C430 - Re-import', 'local_equivalent' => 'reimport'],
            ],

            // ============================================
            // COUNTRY CODES (ISO 2-letter)
            // ============================================
            'Supplier Country' => [
                ['option_value' => 'US', 'option_label' => 'United States', 'local_matches' => ['United States', 'USA', 'US'], 'is_default' => true],
                ['option_value' => 'CA', 'option_label' => 'Canada', 'local_matches' => ['Canada', 'CA']],
                ['option_value' => 'GB', 'option_label' => 'United Kingdom', 'local_matches' => ['United Kingdom', 'UK', 'GB', 'England']],
                ['option_value' => 'CN', 'option_label' => 'China', 'local_matches' => ['China', 'CN', 'PRC']],
                ['option_value' => 'PR', 'option_label' => 'Puerto Rico', 'local_matches' => ['Puerto Rico', 'PR']],
                ['option_value' => 'VI', 'option_label' => 'US Virgin Islands', 'local_matches' => ['USVI', 'Virgin Islands', 'VI']],
                ['option_value' => 'MX', 'option_label' => 'Mexico', 'local_matches' => ['Mexico', 'MX']],
                ['option_value' => 'DE', 'option_label' => 'Germany', 'local_matches' => ['Germany', 'DE']],
                ['option_value' => 'FR', 'option_label' => 'France', 'local_matches' => ['France', 'FR']],
                ['option_value' => 'IT', 'option_label' => 'Italy', 'local_matches' => ['Italy', 'IT']],
                ['option_value' => 'JP', 'option_label' => 'Japan', 'local_matches' => ['Japan', 'JP']],
                ['option_value' => 'TW', 'option_label' => 'Taiwan', 'local_matches' => ['Taiwan', 'TW']],
                ['option_value' => 'KR', 'option_label' => 'South Korea', 'local_matches' => ['Korea', 'South Korea', 'KR']],
                ['option_value' => 'VG', 'option_label' => 'British Virgin Islands', 'local_matches' => ['BVI', 'British Virgin Islands', 'VG']],
                ['option_value' => 'JM', 'option_label' => 'Jamaica', 'local_matches' => ['Jamaica', 'JM']],
                ['option_value' => 'BB', 'option_label' => 'Barbados', 'local_matches' => ['Barbados', 'BB']],
                ['option_value' => 'TT', 'option_label' => 'Trinidad and Tobago', 'local_matches' => ['Trinidad', 'Tobago', 'TT']],
            ],

            // Use same for other country fields
            'Country of Direct Shipment' => [
                ['option_value' => 'US', 'option_label' => 'United States', 'local_matches' => ['United States', 'USA', 'US'], 'is_default' => true],
                ['option_value' => 'PR', 'option_label' => 'Puerto Rico', 'local_matches' => ['Puerto Rico', 'PR']],
                ['option_value' => 'VI', 'option_label' => 'US Virgin Islands', 'local_matches' => ['USVI', 'VI']],
                ['option_value' => 'CA', 'option_label' => 'Canada', 'local_matches' => ['Canada', 'CA']],
                ['option_value' => 'GB', 'option_label' => 'United Kingdom', 'local_matches' => ['UK', 'GB']],
            ],

            'Country of Original Shipment' => [
                ['option_value' => 'US', 'option_label' => 'United States', 'local_matches' => ['United States', 'USA', 'US'], 'is_default' => true],
                ['option_value' => 'CN', 'option_label' => 'China', 'local_matches' => ['China', 'CN']],
                ['option_value' => 'PR', 'option_label' => 'Puerto Rico', 'local_matches' => ['Puerto Rico', 'PR']],
                ['option_value' => 'CA', 'option_label' => 'Canada', 'local_matches' => ['Canada', 'CA']],
            ],

            'Country of Origin (Item)' => [
                ['option_value' => 'US', 'option_label' => 'United States', 'local_matches' => ['United States', 'USA', 'US'], 'is_default' => true],
                ['option_value' => 'CN', 'option_label' => 'China', 'local_matches' => ['China', 'CN', 'Made in China']],
                ['option_value' => 'MX', 'option_label' => 'Mexico', 'local_matches' => ['Mexico', 'MX']],
                ['option_value' => 'CA', 'option_label' => 'Canada', 'local_matches' => ['Canada', 'CA']],
                ['option_value' => 'GB', 'option_label' => 'United Kingdom', 'local_matches' => ['UK', 'GB']],
                ['option_value' => 'DE', 'option_label' => 'Germany', 'local_matches' => ['Germany', 'DE']],
                ['option_value' => 'IT', 'option_label' => 'Italy', 'local_matches' => ['Italy', 'IT']],
                ['option_value' => 'FR', 'option_label' => 'France', 'local_matches' => ['France', 'FR']],
                ['option_value' => 'JP', 'option_label' => 'Japan', 'local_matches' => ['Japan', 'JP']],
                ['option_value' => 'TW', 'option_label' => 'Taiwan', 'local_matches' => ['Taiwan', 'TW']],
                ['option_value' => 'KR', 'option_label' => 'South Korea', 'local_matches' => ['Korea', 'KR']],
                ['option_value' => 'TH', 'option_label' => 'Thailand', 'local_matches' => ['Thailand', 'TH']],
                ['option_value' => 'VN', 'option_label' => 'Vietnam', 'local_matches' => ['Vietnam', 'VN']],
                ['option_value' => 'IN', 'option_label' => 'India', 'local_matches' => ['India', 'IN']],
            ],

            // ============================================
            // PORT OF ARRIVAL
            // ============================================
            'Port of Arrival' => [
                ['option_value' => 'PP', 'option_label' => 'PP - Port Purcell (Road Town)', 'local_matches' => ['Port Purcell', 'Road Town', 'PP', 'Tortola'], 'is_default' => true],
                ['option_value' => 'WE', 'option_label' => 'WE - West End', 'local_matches' => ['West End', 'WE']],
                ['option_value' => 'BA', 'option_label' => 'BA - Beef Island Airport', 'local_matches' => ['Beef Island', 'Airport', 'BA', 'EIS']],
                ['option_value' => 'VG', 'option_label' => 'VG - Virgin Gorda', 'local_matches' => ['Virgin Gorda', 'VG']],
                ['option_value' => 'JV', 'option_label' => 'JV - Jost Van Dyke', 'local_matches' => ['Jost Van Dyke', 'JVD', 'JV']],
                ['option_value' => 'AN', 'option_label' => 'AN - Anegada', 'local_matches' => ['Anegada', 'AN']],
            ],

            // ============================================
            // CARRIER LOOKUP
            // ============================================
            'Carrier ID' => [
                ['option_value' => 'TOR', 'option_label' => 'TOR - Tortola PR', 'local_matches' => ['Tortola PR', 'TOR', 'Tropical']],
                ['option_value' => 'TPL', 'option_label' => 'TPL - Tropical Shipping', 'local_matches' => ['Tropical Shipping', 'TPL', 'Tropical']],
                ['option_value' => 'CRO', 'option_label' => 'CRO - Crowley', 'local_matches' => ['Crowley', 'CRO']],
                ['option_value' => 'FED', 'option_label' => 'FED - FedEx', 'local_matches' => ['FedEx', 'FED', 'Federal Express']],
                ['option_value' => 'UPS', 'option_label' => 'UPS - UPS', 'local_matches' => ['UPS', 'United Parcel']],
                ['option_value' => 'DHL', 'option_label' => 'DHL - DHL', 'local_matches' => ['DHL']],
                ['option_value' => 'AAL', 'option_label' => 'AAL - American Airlines', 'local_matches' => ['American Airlines', 'AAL', 'AA']],
                ['option_value' => 'CAP', 'option_label' => 'CAP - Cape Air', 'local_matches' => ['Cape Air', 'CAP']],
            ],

            // ============================================
            // METHOD OF PAYMENT
            // ============================================
            'Method of Payment' => [
                ['option_value' => 'CSH', 'option_label' => 'CSH - Cash', 'local_matches' => ['Cash', 'CSH'], 'is_default' => true],
                ['option_value' => 'CHK', 'option_label' => 'CHK - Cheque', 'local_matches' => ['Cheque', 'Check', 'CHK']],
                ['option_value' => 'CRD', 'option_label' => 'CRD - Credit Card', 'local_matches' => ['Credit', 'Card', 'CRD']],
                ['option_value' => 'BNK', 'option_label' => 'BNK - Bank Transfer', 'local_matches' => ['Bank', 'Transfer', 'Wire', 'BNK']],
                ['option_value' => 'ACC', 'option_label' => 'ACC - On Account', 'local_matches' => ['Account', 'ACC']],
            ],

            // ============================================
            // CURRENCY
            // ============================================
            'Currency' => [
                ['option_value' => 'USD', 'option_label' => 'USD - US Dollar', 'local_matches' => ['USD', 'US Dollar', '$'], 'is_default' => true],
                ['option_value' => 'EUR', 'option_label' => 'EUR - Euro', 'local_matches' => ['EUR', 'Euro', '€']],
                ['option_value' => 'GBP', 'option_label' => 'GBP - British Pound', 'local_matches' => ['GBP', 'Pound', '£']],
                ['option_value' => 'CAD', 'option_label' => 'CAD - Canadian Dollar', 'local_matches' => ['CAD', 'Canadian']],
            ],

            // ============================================
            // CHARGE CODES
            // ============================================
            'Freight Charge Code' => [
                ['option_value' => 'FRT', 'option_label' => 'FRT - Freight Charges', 'is_default' => true],
                ['option_value' => 'INS', 'option_label' => 'INS - Insurance Charges'],
                ['option_value' => 'EXE', 'option_label' => 'EXE - Exemption Amount'],
                ['option_value' => 'HIR', 'option_label' => 'HIR - Hire Costs'],
                ['option_value' => 'REP', 'option_label' => 'REP - Repair Costs'],
                ['option_value' => 'PAK', 'option_label' => 'PAK - Packing'],
                ['option_value' => 'HND', 'option_label' => 'HND - Handling'],
            ],

            'Insurance Charge Code' => [
                ['option_value' => 'INS', 'option_label' => 'INS - Insurance Charges', 'is_default' => true],
                ['option_value' => 'FRT', 'option_label' => 'FRT - Freight Charges'],
            ],

            // ============================================
            // TAX TYPES
            // ============================================
            'Tax Type 1 (Customs Duty)' => [
                ['option_value' => 'CUD', 'option_label' => 'CUD - Customs Duty', 'is_default' => true],
                ['option_value' => 'EXC', 'option_label' => 'EXC - Excise Tax'],
                ['option_value' => 'SUR', 'option_label' => 'SUR - Surcharge'],
                ['option_value' => 'ENV', 'option_label' => 'ENV - Environmental Levy'],
                ['option_value' => 'STA', 'option_label' => 'STA - Stamp Duty'],
            ],

            'Tax Type 2 (Wharfage)' => [
                ['option_value' => 'WHA', 'option_label' => 'WHA - Wharfage (2%)', 'is_default' => true],
                ['option_value' => 'CUD', 'option_label' => 'CUD - Customs Duty'],
            ],

            // ============================================
            // UNITS OF MEASURE
            // ============================================
            'Units' => [
                ['option_value' => 'EA', 'option_label' => 'EA - Each', 'local_matches' => ['Each', 'EA', 'Unit', 'PC', 'Piece'], 'is_default' => true],
                ['option_value' => 'KG', 'option_label' => 'KG - Kilogram', 'local_matches' => ['Kilogram', 'KG', 'Kilo']],
                ['option_value' => 'LB', 'option_label' => 'LB - Pound', 'local_matches' => ['Pound', 'LB', 'Lb']],
                ['option_value' => 'LT', 'option_label' => 'LT - Liter', 'local_matches' => ['Liter', 'Litre', 'LT', 'L']],
                ['option_value' => 'MT', 'option_label' => 'MT - Meter', 'local_matches' => ['Meter', 'Metre', 'MT', 'M']],
                ['option_value' => 'DZ', 'option_label' => 'DZ - Dozen', 'local_matches' => ['Dozen', 'DZ']],
                ['option_value' => 'CS', 'option_label' => 'CS - Case', 'local_matches' => ['Case', 'CS']],
                ['option_value' => 'BX', 'option_label' => 'BX - Box', 'local_matches' => ['Box', 'BX']],
                ['option_value' => 'CT', 'option_label' => 'CT - Carton', 'local_matches' => ['Carton', 'CT', 'CTN']],
                ['option_value' => 'PL', 'option_label' => 'PL - Pallet', 'local_matches' => ['Pallet', 'PL']],
            ],

            // ============================================
            // PACKAGE TYPES
            // ============================================
            'Type of Packages' => [
                ['option_value' => 'CTN', 'option_label' => 'CTN - Carton', 'local_matches' => ['Carton', 'CTN', 'Box'], 'is_default' => true],
                ['option_value' => 'CS', 'option_label' => 'CS - Case', 'local_matches' => ['Case', 'CS']],
                ['option_value' => 'BX', 'option_label' => 'BX - Box', 'local_matches' => ['Box', 'BX']],
                ['option_value' => 'PK', 'option_label' => 'PK - Package', 'local_matches' => ['Package', 'PK', 'PKG']],
                ['option_value' => 'PL', 'option_label' => 'PL - Pallet', 'local_matches' => ['Pallet', 'PL', 'PLT']],
                ['option_value' => 'DR', 'option_label' => 'DR - Drum', 'local_matches' => ['Drum', 'DR']],
                ['option_value' => 'BG', 'option_label' => 'BG - Bag', 'local_matches' => ['Bag', 'BG']],
                ['option_value' => 'RL', 'option_label' => 'RL - Roll', 'local_matches' => ['Roll', 'RL']],
                ['option_value' => 'EA', 'option_label' => 'EA - Each', 'local_matches' => ['Each', 'EA']],
                ['option_value' => 'PC', 'option_label' => 'PC - Piece', 'local_matches' => ['Piece', 'PC', 'Pieces']],
                ['option_value' => 'SK', 'option_label' => 'SK - Skid', 'local_matches' => ['Skid', 'SK']],
                ['option_value' => 'CR', 'option_label' => 'CR - Crate', 'local_matches' => ['Crate', 'CR']],
            ],

            // ============================================
            // ADDITIONAL INFORMATION CODES
            // ============================================
            'Additional Information' => [
                ['option_value' => 'RMK', 'option_label' => 'RMK - Remark'],
                ['option_value' => 'LIC', 'option_label' => 'LIC - License Number'],
                ['option_value' => 'PER', 'option_label' => 'PER - Permit Number'],
                ['option_value' => 'EXM', 'option_label' => 'EXM - Exemption Reference'],
                ['option_value' => 'INV', 'option_label' => 'INV - Invoice Reference'],
                ['option_value' => 'ORD', 'option_label' => 'ORD - Order Number'],
            ],

            // ============================================
            // EXEMPTION INDICATORS
            // ============================================
            'Tax Exempt Ind. 1' => [
                ['option_value' => '', 'option_label' => '(None)', 'is_default' => true],
                ['option_value' => 'GS', 'option_label' => 'GS - Government'],
                ['option_value' => 'CH', 'option_label' => 'CH - Charitable'],
                ['option_value' => 'ED', 'option_label' => 'ED - Educational'],
                ['option_value' => 'HO', 'option_label' => 'HO - Hotel'],
                ['option_value' => 'IN', 'option_label' => 'IN - Industrial'],
                ['option_value' => 'AG', 'option_label' => 'AG - Agricultural'],
            ],
        ];

        $count = 0;
        foreach ($dropdowns as $fieldLabel => $options) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $fieldLabel)
                ->first();

            if (!$mapping) {
                $this->warn("Mapping not found for: {$fieldLabel}");
                continue;
            }

            foreach ($options as $index => $option) {
                WebFormDropdownValue::updateOrCreate(
                    [
                        'web_form_field_mapping_id' => $mapping->id,
                        'option_value' => $option['option_value'],
                    ],
                    [
                        'option_label' => $option['option_label'],
                        'local_equivalent' => $option['local_equivalent'] ?? null,
                        'local_matches' => $option['local_matches'] ?? null,
                        'sort_order' => $index,
                        'is_default' => $option['is_default'] ?? false,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }
}
