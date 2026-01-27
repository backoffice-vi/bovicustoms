<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;
use App\Models\Country;

class SetupCapsTarget extends Command
{
    protected $signature = 'caps:setup {--username= : CAPS username} {--password= : CAPS password}';
    protected $description = 'Set up the CAPS BVI Web Form Target with all pages and field mappings';

    public function handle()
    {
        $this->info('Setting up CAPS BVI Web Form Target...');

        // Get BVI country
        $country = Country::where('name', 'like', '%British Virgin%')->first();
        if (!$country) {
            $this->error('BVI country not found in database. Please create it first.');
            return 1;
        }

        // Get credentials
        $username = $this->option('username') ?: $this->ask('CAPS Username (email)');
        $password = $this->option('password') ?: $this->secret('CAPS Password');

        // Create or update the target
        $target = WebFormTarget::updateOrCreate(
            ['code' => 'caps_bvi'],
            [
                'country_id' => $country->id,
                'name' => 'CAPS BVI - Customs Automated Processing System',
                'base_url' => 'https://caps.gov.vg',
                'login_url' => '/CAPSWeb/TraderLogin.jsp',
                'auth_type' => 'form',
                'credentials' => [
                    'username' => $username,
                    'password' => $password,
                    'username_field' => 'input[placeholder="User ID"]',
                    'password_field' => 'input[placeholder="Password"]',
                    'submit_selector' => 'button:has-text("Login")',
                ],
                'requires_ai' => true,
                'is_active' => true,
                'workflow_steps' => [
                    ['action' => 'login', 'page' => 'login'],
                    ['action' => 'navigate', 'page' => 'td_list'],
                    ['action' => 'new', 'page' => 'td_entry'],
                    ['action' => 'fill', 'page' => 'td_entry'],
                    ['action' => 'save', 'page' => 'td_entry'],
                ],
                'notes' => 'BVI Customs CAPS system. Auto-configured via caps:setup command.',
            ]
        );

        $this->info("Created/updated target: {$target->name} (ID: {$target->id})");

        // Create Login Page
        $loginPage = $this->createLoginPage($target);
        $this->info("Created login page: {$loginPage->name}");

        // Create TD List Page
        $tdListPage = $this->createTDListPage($target);
        $this->info("Created TD list page: {$tdListPage->name}");

        // Create TD Data Entry Page with all field mappings
        $tdEntryPage = $this->createTDEntryPage($target);
        $this->info("Created TD entry page: {$tdEntryPage->name}");

        // Create field mappings
        $mappingCount = $this->createFieldMappings($tdEntryPage);
        $this->info("Created {$mappingCount} field mappings");

        // Create dropdown values for lookups
        $dropdownCount = $this->createDropdownValues($tdEntryPage);
        $this->info("Created {$dropdownCount} dropdown values");

        // Mark as mapped
        $target->markMapped();

        $this->info('');
        $this->info('CAPS configuration complete!');
        $this->info("Target ID: {$target->id}");
        $this->info("Total Pages: {$target->pages()->count()}");
        $this->info("Total Field Mappings: {$target->getAllFieldMappings()->count()}");

        return 0;
    }

    protected function createLoginPage(WebFormTarget $target): WebFormPage
    {
        return WebFormPage::updateOrCreate(
            ['web_form_target_id' => $target->id, 'url_pattern' => '/CAPSWeb/TraderLogin.jsp'],
            [
                'name' => 'CAPS Login',
                'page_type' => 'login',
                'sequence_order' => 1,
                'submit_selector' => 'button:has-text("Login")',
                'success_indicator' => 'text=Welcome back',
                'error_indicator' => 'text=Invalid',
                'is_active' => true,
            ]
        );
    }

    protected function createTDListPage(WebFormTarget $target): WebFormPage
    {
        return WebFormPage::updateOrCreate(
            ['web_form_target_id' => $target->id, 'url_pattern' => '/CAPSWeb/WebTraderServlet'],
            [
                'name' => 'TD List',
                'page_type' => 'search',
                'sequence_order' => 2,
                'submit_selector' => 'button:has-text("New")',
                'success_indicator' => 'text=TD List',
                'error_indicator' => '.error',
                'navigation' => [
                    'method' => 'auto', // After login, redirects here automatically
                ],
                'is_active' => true,
            ]
        );
    }

    protected function createTDEntryPage(WebFormTarget $target): WebFormPage
    {
        return WebFormPage::updateOrCreate(
            ['web_form_target_id' => $target->id, 'url_pattern' => '/CAPSWeb/TDDataEntryServlet'],
            [
                'name' => 'TD Data Entry',
                'page_type' => 'form',
                'sequence_order' => 3,
                'submit_selector' => 'button:has-text("Save (1)")',
                'success_indicator' => 'text=saved successfully',
                'error_indicator' => '.error, text=Error',
                'navigation' => [
                    'method' => 'url',
                    'url_template' => '/CAPSWeb/TDDataEntryServlet?method=tddataentry.RetrieveTD&bcdNumber={td_number}&isWebTrader=Y',
                    'new_url' => '/CAPSWeb/TDDataEntryServlet?method=tddataentry.NewTD&isWebTrader=Y',
                ],
                'is_active' => true,
            ]
        );
    }

    protected function createFieldMappings(WebFormPage $page): int
    {
        $mappings = [
            // ============================================
            // SUMMARY DETAIL SECTION
            // ============================================
            [
                'section' => 'Summary Detail',
                'web_field_label' => 'TD Type',
                'web_field_name' => 'tdType',
                'web_field_selectors' => ['select[name*="tdType"]', 'select:near(:text("TD Type"))'],
                'field_type' => 'select',
                'local_field' => null,
                'static_value' => 'Import',
                'is_required' => true,
                'tab_order' => 1,
            ],
            [
                'section' => 'Summary Detail',
                'web_field_label' => 'Trader Reference',
                'web_field_name' => 'traderReference',
                'web_field_selectors' => ['input[name*="traderRef"]', 'input:near(:text("Trader Reference"))'],
                'field_type' => 'text',
                'local_field' => 'declaration.form_number',
                'is_required' => false,
                'tab_order' => 2,
            ],
            [
                'section' => 'Summary Detail',
                'web_field_label' => 'Related TD No.',
                'web_field_name' => 'relatedTdNo',
                'web_field_selectors' => ['input[name*="relatedTd"]', 'input:near(:text("Related TD"))'],
                'field_type' => 'text',
                'local_field' => null,
                'is_required' => false,
                'tab_order' => 3,
            ],

            // ============================================
            // SECTION 1: SUPPLIER
            // ============================================
            [
                'section' => '1. Supplier',
                'web_field_label' => 'Supplier ID',
                'web_field_name' => 'supplierId',
                'web_field_selectors' => ['input[name*="supplierId"]', 'input:near(:text("SUPPLIER ID"))'],
                'field_type' => 'text',
                'local_field' => 'shipper.customs_registration_id',
                'is_required' => false,
                'tab_order' => 10,
            ],
            [
                'section' => '1. Supplier',
                'web_field_label' => 'Supplier Name',
                'web_field_name' => 'supplierName',
                'web_field_selectors' => ['input[name*="supplierName"]', 'input:near(:text("a. NAME")):first'],
                'field_type' => 'text',
                'local_field' => 'shipper.company_name',
                'is_required' => true,
                'tab_order' => 11,
            ],
            [
                'section' => '1. Supplier',
                'web_field_label' => 'Supplier Street',
                'web_field_name' => 'supplierStreet',
                'web_field_selectors' => ['input[name*="supplierStreet"]', 'input:near(:text("b. STREET"))'],
                'field_type' => 'text',
                'local_field' => 'shipper.address_line_1',
                'is_required' => false,
                'tab_order' => 12,
            ],
            [
                'section' => '1. Supplier',
                'web_field_label' => 'Supplier City/State',
                'web_field_name' => 'supplierCityState',
                'web_field_selectors' => ['input[name*="supplierCity"]', 'input:near(:text("c. CITY"))'],
                'field_type' => 'text',
                'local_field' => 'shipper.city',
                'is_required' => false,
                'tab_order' => 13,
            ],
            [
                'section' => '1. Supplier',
                'web_field_label' => 'Supplier ZIP/Post Code',
                'web_field_name' => 'supplierZip',
                'web_field_selectors' => ['input[name*="supplierZip"]', 'input:near(:text("d. ZIP"))'],
                'field_type' => 'text',
                'local_field' => 'shipper.postal_code',
                'is_required' => false,
                'tab_order' => 14,
            ],
            [
                'section' => '1. Supplier',
                'web_field_label' => 'Supplier Country',
                'web_field_name' => 'supplierCountry',
                'web_field_selectors' => ['input[name*="supplierCountry"]', 'input:near(:text("e. COUNTRY"))'],
                'field_type' => 'text',
                'local_field' => 'shipper.country',
                'default_value' => 'US',
                'is_required' => true,
                'tab_order' => 15,
            ],

            // ============================================
            // SECTION 2: IMPORTER
            // ============================================
            [
                'section' => '2. Importer',
                'web_field_label' => 'Importer ID',
                'web_field_name' => 'importerId',
                'web_field_selectors' => ['input[name*="importerId"]', 'input:near(:text("IMPORTER ID"))'],
                'field_type' => 'text',
                'local_field' => null,
                'static_value' => '100184', // Nature's Way Ltd ID
                'is_required' => true,
                'tab_order' => 20,
            ],

            // ============================================
            // SECTION 3: TRANSPORT DETAILS
            // ============================================
            [
                'section' => '3. Transport',
                'web_field_label' => 'Carrier ID',
                'web_field_name' => 'carrierId',
                'web_field_selectors' => ['input[name*="carrierId"]', 'input:near(:text("CARRIER ID")):first'],
                'field_type' => 'text',
                'local_field' => 'shipment.carrier_name',
                'is_required' => false,
                'tab_order' => 30,
            ],
            [
                'section' => '3. Transport',
                'web_field_label' => 'Port of Arrival',
                'web_field_name' => 'portOfArrival',
                'web_field_selectors' => ['input[name*="portOfArrival"]', 'input:near(:text("PORT OF ARRIVAL"))'],
                'field_type' => 'text',
                'local_field' => 'shipment.port_of_discharge',
                'default_value' => 'PP', // Port Purcell
                'is_required' => true,
                'tab_order' => 31,
            ],
            [
                'section' => '3. Transport',
                'web_field_label' => 'Arrival Date',
                'web_field_name' => 'arrivalDate',
                'web_field_selectors' => ['input[name*="arrivalDate"]', 'input:near(:text("ARRIVAL DATE"))'],
                'field_type' => 'date',
                'local_field' => 'shipment.actual_arrival_date',
                'value_transform' => ['type' => 'date_format', 'format' => 'd/m/Y'],
                'is_required' => true,
                'tab_order' => 32,
            ],

            // ============================================
            // SECTION 4: MANIFEST
            // ============================================
            [
                'section' => '4. Manifest',
                'web_field_label' => 'Manifest No.',
                'web_field_name' => 'manifestNo',
                'web_field_selectors' => ['input[name*="manifestNo"]', 'input:near(:text("MANIFEST NO"))'],
                'field_type' => 'text',
                'local_field' => 'shipment.manifest_number',
                'is_required' => false,
                'tab_order' => 40,
            ],
            [
                'section' => '4. Manifest',
                'web_field_label' => 'No. of Packages',
                'web_field_name' => 'noOfPackages',
                'web_field_selectors' => ['input[name*="noOfPackages"]', 'input:near(:text("NO. OF PACKAGES"))'],
                'field_type' => 'number',
                'local_field' => 'shipment.total_packages',
                'is_required' => true,
                'tab_order' => 41,
            ],
            [
                'section' => '4. Manifest',
                'web_field_label' => 'Bill of Lading / AWB',
                'web_field_name' => 'billOfLading',
                'web_field_selectors' => ['input[name*="billOfLading"]', 'input[name*="awb"]', 'input:near(:text("BILL OF LADING"))'],
                'field_type' => 'text',
                'local_field' => 'shipment.bill_of_lading_number',
                'is_required' => true,
                'tab_order' => 42,
            ],
            [
                'section' => '4. Manifest',
                'web_field_label' => 'Container ID',
                'web_field_name' => 'containerId',
                'web_field_selectors' => ['input[name*="containerId"]', 'input[placeholder*="AAAA"]', 'input:near(:text("CONTAINER ID"))'],
                'field_type' => 'text',
                'local_field' => 'shipment.container_id',
                'is_required' => false,
                'tab_order' => 43,
            ],

            // ============================================
            // SECTION 5: SHIPMENT ORIGIN
            // ============================================
            [
                'section' => '5. Origin',
                'web_field_label' => 'City of Direct Shipment',
                'web_field_name' => 'cityOfDirectShipment',
                'web_field_selectors' => ['input[name*="cityOfDirect"]', 'input:near(:text("CITY OF DIRECT"))'],
                'field_type' => 'text',
                'local_field' => 'shipment.port_of_loading',
                'is_required' => false,
                'tab_order' => 50,
            ],
            [
                'section' => '5. Origin',
                'web_field_label' => 'Country of Direct Shipment',
                'web_field_name' => 'countryOfDirectShipment',
                'web_field_selectors' => ['input[name*="countryOfDirect"]', 'input:near(:text("COUNTRY OF DIRECT"))'],
                'field_type' => 'text',
                'local_field' => null,
                'default_value' => 'US',
                'is_required' => true,
                'tab_order' => 51,
            ],
            [
                'section' => '5. Origin',
                'web_field_label' => 'Country of Original Shipment',
                'web_field_name' => 'countryOfOriginalShipment',
                'web_field_selectors' => ['input[name*="countryOfOriginal"]', 'input:near(:text("COUNTRY OF ORIGINAL"))'],
                'field_type' => 'text',
                'local_field' => 'declaration.country_of_origin',
                'default_value' => 'US',
                'is_required' => true,
                'tab_order' => 52,
            ],

            // ============================================
            // SECTION 6: ADDITIONAL INFO
            // ============================================
            [
                'section' => '6. Additional',
                'web_field_label' => 'Method of Payment',
                'web_field_name' => 'methodOfPayment',
                'web_field_selectors' => ['input[name*="methodOfPayment"]', 'input:near(:text("Method of Payment"))'],
                'field_type' => 'text',
                'local_field' => null,
                'default_value' => 'CSH', // Cash
                'is_required' => false,
                'tab_order' => 60,
            ],

            // ============================================
            // SECTION 7-10: TOTALS
            // ============================================
            [
                'section' => '7-10. Totals',
                'web_field_label' => 'Total Freight',
                'web_field_name' => 'totalFreight',
                'web_field_selectors' => ['input[name*="totalFreight"]', 'input:near(:text("TOTAL FREIGHT"))'],
                'field_type' => 'number',
                'local_field' => 'declaration.freight_total',
                'is_required' => true,
                'tab_order' => 70,
            ],
            [
                'section' => '7-10. Totals',
                'web_field_label' => 'Freight Prorated',
                'web_field_name' => 'freightProrated',
                'web_field_selectors' => ['input[type="checkbox"]:near(:text("FREIGHT")):near(:text("PRORATED"))'],
                'field_type' => 'checkbox',
                'local_field' => null,
                'static_value' => 'true',
                'is_required' => false,
                'tab_order' => 71,
            ],
            [
                'section' => '7-10. Totals',
                'web_field_label' => 'Total Insurance',
                'web_field_name' => 'totalInsurance',
                'web_field_selectors' => ['input[name*="totalInsurance"]', 'input:near(:text("TOTAL INSURANCE"))'],
                'field_type' => 'number',
                'local_field' => 'declaration.insurance_total',
                'is_required' => true,
                'tab_order' => 72,
            ],
            [
                'section' => '7-10. Totals',
                'web_field_label' => 'Insurance Prorated',
                'web_field_name' => 'insuranceProrated',
                'web_field_selectors' => ['input[type="checkbox"]:near(:text("INSURANCE")):near(:text("PRORATED"))'],
                'field_type' => 'checkbox',
                'local_field' => null,
                'static_value' => 'true',
                'is_required' => false,
                'tab_order' => 73,
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

    protected function createDropdownValues(WebFormPage $page): int
    {
        $dropdowns = [
            'TD Type' => [
                ['option_value' => 'Import', 'option_label' => 'Import', 'local_equivalent' => 'import', 'is_default' => true],
                ['option_value' => 'Export', 'option_label' => 'Export', 'local_equivalent' => 'export'],
                ['option_value' => 'Deposit', 'option_label' => 'Deposit', 'local_equivalent' => 'deposit'],
                ['option_value' => 'Adjustment', 'option_label' => 'Adjustment', 'local_equivalent' => 'adjustment'],
            ],
            'Port of Arrival' => [
                ['option_value' => 'PP', 'option_label' => 'Port Purcell (Road Town)', 'local_equivalent' => 'Road Town', 'local_matches' => ['Port Purcell', 'PP', 'Road Town'], 'is_default' => true],
                ['option_value' => 'WE', 'option_label' => 'West End', 'local_equivalent' => 'West End', 'local_matches' => ['West End', 'WE']],
            ],
            'Method of Payment' => [
                ['option_value' => 'CSH', 'option_label' => 'Cash', 'local_equivalent' => 'cash', 'is_default' => true],
                ['option_value' => 'CHK', 'option_label' => 'Cheque', 'local_equivalent' => 'cheque'],
                ['option_value' => 'CRD', 'option_label' => 'Credit', 'local_equivalent' => 'credit'],
            ],
            'Country of Direct Shipment' => [
                ['option_value' => 'US', 'option_label' => 'United States', 'local_equivalent' => 'US', 'local_matches' => ['United States', 'USA', 'US'], 'is_default' => true],
                ['option_value' => 'PR', 'option_label' => 'Puerto Rico', 'local_equivalent' => 'PR', 'local_matches' => ['Puerto Rico', 'PR']],
                ['option_value' => 'VI', 'option_label' => 'US Virgin Islands', 'local_equivalent' => 'VI', 'local_matches' => ['USVI', 'VI', 'Virgin Islands']],
            ],
            'Supplier Country' => [
                ['option_value' => 'US', 'option_label' => 'United States', 'local_equivalent' => 'US', 'is_default' => true],
                ['option_value' => 'CN', 'option_label' => 'China', 'local_equivalent' => 'CN'],
                ['option_value' => 'GB', 'option_label' => 'United Kingdom', 'local_equivalent' => 'GB'],
                ['option_value' => 'PR', 'option_label' => 'Puerto Rico', 'local_equivalent' => 'PR'],
            ],
        ];

        $count = 0;
        foreach ($dropdowns as $fieldLabel => $options) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $fieldLabel)
                ->first();

            if (!$mapping) {
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
