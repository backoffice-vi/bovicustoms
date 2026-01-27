<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsChargeCodes extends Command
{
    protected $signature = 'caps:import-charges';
    protected $description = 'Import CAPS Charge Codes';

    public function handle()
    {
        $this->info('Importing CAPS Charge codes...');

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
EXE EXEMPTION AMOUNT
FRT FREIGHT CHARGES
HIR HIRE COSTS
INS INSURANCE CHARGES
REP REPAIR COSTS
EOT;

        // Apply to both Freight and Insurance charge lookups
        $fields = ['Freight Charge Code', 'Insurance Charge Code'];

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
                    
                    // Set defaults based on field type
                    $isDefault = false;
                    if ($fieldLabel === 'Freight Charge Code' && $code === 'FRT') $isDefault = true;
                    if ($fieldLabel === 'Insurance Charge Code' && $code === 'INS') $isDefault = true;

                    WebFormDropdownValue::create([
                        'web_form_field_mapping_id' => $mapping->id,
                        'option_value' => $code,
                        'option_label' => "$code - $name",
                        'local_matches' => $localMatches,
                        'sort_order' => $index,
                        'is_default' => $isDefault,
                    ]);
                }
                $this->info("Imported " . count($lines) . " charge codes for $fieldLabel.");
            }
        }
    }
}
