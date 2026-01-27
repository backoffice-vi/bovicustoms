<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsExemptIds extends Command
{
    protected $signature = 'caps:import-exempt-ids';
    protected $description = 'Import CAPS Exempt IDs';

    public function handle()
    {
        $this->info('Importing CAPS Exempt IDs...');

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
0 EXEMPT 18%
1 EXEMPT 75%
2 EXEMPT 50%
3 EXEMPT 90%
4 EXEMPT 70%
5 EXEMPT 40%
6 EXEMPT 15%
7 EXEMPT 60%
8 EXEMPT 25%
9 EXEMPT 20%
A EXEMPT 53%
B Base Amount
C EXEMPT 65%
E EXEMPT
F EXEMPT 16%
R Residual Amount
EOT;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Tax Exempt Ind. 1')
            ->first();

        if ($mapping) {
            // Clear existing
            WebFormDropdownValue::where('web_form_field_mapping_id', $mapping->id)
                ->where('is_default', false)
                ->delete();

            $lines = explode("\n", $codesRaw);
            
            // Add a "None" option
            WebFormDropdownValue::create([
                'web_form_field_mapping_id' => $mapping->id,
                'option_value' => '',
                'option_label' => '(None)',
                'sort_order' => -1,
                'is_default' => true,
            ]);

            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Split by first space
                $parts = explode(' ', $line, 2);
                $code = $parts[0];
                $name = $parts[1] ?? '';

                $localMatches = [$name, $code];
                
                // Match "Exempt" to "E" broadly if no specific percentage is found
                if ($code === 'E') $localMatches[] = 'Exempt';

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                    'is_default' => false,
                ]);
            }
            $this->info("Imported " . count($lines) . " exempt IDs.");
        }
    }
}
