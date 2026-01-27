<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsUnits extends Command
{
    protected $signature = 'caps:import-units';
    protected $description = 'Import CAPS Unit of Measure codes';

    public function handle()
    {
        $this->info('Importing CAPS Unit of Measure codes...');

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
100LB 100 POUNDS
GAL GALLON
LB POUND
UNIT HEAD/UNIT (EACH)
EOT;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Units')
            ->first();

        if ($mapping) {
            // Clear existing assumed values
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
                
                // Common variations logic
                if ($code === 'UNIT') $localMatches = array_merge($localMatches, ['EA', 'Each', 'Piece', 'PC']);
                if ($code === 'LB') $localMatches[] = 'lbs';
                if ($code === 'GAL') $localMatches[] = 'Gallons';

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                    'is_default' => ($code === 'UNIT'), 
                ]);
            }
            $this->info("Imported " . count($lines) . " unit codes.");
        }
    }
}
