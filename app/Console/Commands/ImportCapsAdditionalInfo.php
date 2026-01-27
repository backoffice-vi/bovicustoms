<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsAdditionalInfo extends Command
{
    protected $signature = 'caps:import-additional-info';
    protected $description = 'Import CAPS Additional Info codes for Section 6';

    public function handle()
    {
        $this->info('Importing CAPS Additional Info codes...');

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

        $this->importCodes($page);

        return 0;
    }

    protected function importCodes($page)
    {
        $codesRaw = <<<EOT
AGR AGRICULTURAL CERTIFICATE
CAF CONSERVATION & FISHERIES CERT.
CIC CITES CERTIFICATE
COL COLOUR
CTR ADDITIONAL CONTAINER NUMBER
DEP DEPOSIT DETAILS
EXE EXEMPTION DETAILS
EXP EXPLOSIVES PERMIT
FAP FIREARMS IMPORT PERMIT
FMB FIRST TIME HOME BUILDERS
GOV GOVERNMENT CERTIFICATION STAMP
HUL HULL IDENTIFICATION NUMBER
IDN IMPORTER IDENTIFICATION NUMBER
IMN IMPORTER NAME
INV INVOICE NUMBER
LIC TRADE LICENSE NUMBER
MAK MAKE
MOD MODEL
PCD PURCHASE COST DUTY
PCT TOTAL PURCHASE COST DUTY
PCV PURCHASE COST VALUE
PER PERMIT
PSC PHYTO-SANITARY CERTIFICATE
RTD RELATED TD
SEN SERIAL NUMBER
SUP ADDITIONAL SUPPLIER DETAILS
TXT OTHER TEXT
VIN VEHICLE IDENTIFICATION NUMBER
YEA YEAR
EOT;

        // Find the mapping for Section 6 Additional Info
        // Note: In our setup script, we might not have created this specific field mapping yet 
        // because it's a dynamic row. Let's check or create it.
        
        // We'll look for a mapping that represents the "Code" field of the Additional Info section
        // Based on the screenshot, it's the small box before the "Lookup" button.
        $mapping = WebFormFieldMapping::firstOrCreate(
            [
                'web_form_page_id' => $page->id,
                'web_field_label' => 'Additional Info Code',
            ],
            [
                'web_field_name' => 'additionalInfoCode', // Placeholder, selectors matter more
                'web_field_selectors' => ['input:near(:text("ADDITIONAL INFORMATION")):first'],
                'field_type' => 'text',
                'section' => '6. Additional',
                'is_active' => true,
                'tab_order' => 65
            ]
        );

        if ($mapping) {
            WebFormDropdownValue::where('web_form_field_mapping_id', $mapping->id)
                ->where('is_default', false)
                ->delete();

            $lines = explode("\n", $codesRaw);
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $parts = explode(' ', $line, 2);
                $code = $parts[0];
                $name = $parts[1] ?? '';

                $localMatches = [$name, $code];
                
                // Common variations logic can go here if needed
                if ($code === 'INV') $localMatches[] = 'Invoice';
                if ($code === 'LIC') $localMatches[] = 'License';

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                ]);
            }
            $this->info("Imported " . count($lines) . " additional info codes.");
        }
    }
}
