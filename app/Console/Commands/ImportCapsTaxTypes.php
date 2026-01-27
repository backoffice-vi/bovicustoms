<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsTaxTypes extends Command
{
    protected $signature = 'caps:import-tax-types';
    protected $description = 'Import CAPS Tax Type codes';

    public function handle()
    {
        $this->info('Importing CAPS Tax Type codes...');

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
CUD CUSTOMS DUTY
DEP DEPOSIT
FFS FOSSIL FUEL SURCHARGE
WHA WHARFAGE
EOT;

        // Apply to both Tax Type 1 and Tax Type 2
        $fields = ['Tax Type 1 (Customs Duty)', 'Tax Type 2 (Wharfage)'];

        foreach ($fields as $fieldLabel) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $fieldLabel)
                ->first();

            if ($mapping) {
                // Clear existing
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
                    
                    // Set defaults
                    $isDefault = false;
                    if ($fieldLabel === 'Tax Type 1 (Customs Duty)' && $code === 'CUD') $isDefault = true;
                    if ($fieldLabel === 'Tax Type 2 (Wharfage)' && $code === 'WHA') $isDefault = true;

                    WebFormDropdownValue::create([
                        'web_form_field_mapping_id' => $mapping->id,
                        'option_value' => $code,
                        'option_label' => "$code - $name",
                        'local_matches' => $localMatches,
                        'sort_order' => $index,
                        'is_default' => $isDefault,
                    ]);
                }
                $this->info("Imported " . count($lines) . " tax type codes for $fieldLabel.");
            }
        }
    }
}
