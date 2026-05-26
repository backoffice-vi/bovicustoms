<?php

namespace App\Console\Commands;

use App\Models\CountryReferenceData;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormTarget;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCapsPaymentMethods extends Command
{
    protected $signature = 'caps:import-payment-methods
        {--source=storage/app/HMC_100V.3_2024_alpha.xlsx : Path to HMC Excel file}
        {--country-id=1 : Country to import for}';

    protected $description = 'Import official CAPS Payment Methods from the HMC Excel (PAYMENT_METHOD sheet). Replaces the legacy hard-coded fakes.';

    public function handle(): int
    {
        $countryId = (int) $this->option('country-id');
        $sourcePath = $this->option('source');

        if (!is_file($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");
            return self::FAILURE;
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $ss = $reader->load($sourcePath);
        $sh = $ss->getSheetByName('PAYMENT_METHOD');
        if (!$sh) {
            $this->error('PAYMENT_METHOD sheet not found in Excel');
            return self::FAILURE;
        }

        $rows = [];
        foreach ($sh->getRowIterator(2) as $r) {
            $cells = [];
            foreach ($r->getCellIterator() as $c) {
                $cells[] = (string) $c->getValue();
            }
            $code = trim($cells[0] ?? '');
            $label = trim($cells[1] ?? '');
            if ($code === '' || strtoupper($code) === 'CODE') {
                continue;
            }
            $rows[$code] = $label;
        }

        $this->info("Parsed " . count($rows) . " payment methods from Excel.");

        $inserted = 0;
        $updated = 0;
        foreach ($rows as $code => $label) {
            $existing = CountryReferenceData::where('country_id', $countryId)
                ->where('reference_type', CountryReferenceData::TYPE_PAYMENT_METHOD)
                ->where('code', $code)
                ->first();

            $localMatches = array_values(array_unique(array_filter([$label, $code])));

            if (!$existing) {
                CountryReferenceData::create([
                    'country_id' => $countryId,
                    'reference_type' => CountryReferenceData::TYPE_PAYMENT_METHOD,
                    'code' => $code,
                    'label' => $label,
                    'local_matches' => $localMatches,
                    'is_active' => true,
                    'is_default' => $code === '10',
                    'sort_order' => 0,
                    'notes' => 'Imported from HMC v3 alpha 2024',
                ]);
                $inserted++;
            } else {
                $existing->label = $label;
                if (empty($existing->local_matches) || !is_array($existing->local_matches)) {
                    $existing->local_matches = $localMatches;
                }
                $existing->is_active = true;
                $existing->save();
                $updated++;
            }
        }

        $this->info("Inserted {$inserted} new, updated {$updated} existing payment methods.");

        $this->updateMappings($countryId);

        return self::SUCCESS;
    }

    protected function updateMappings(int $countryId): void
    {
        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            return;
        }

        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) {
            return;
        }

        foreach (['Payment Method', 'Method of Payment'] as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_PAYMENT_METHOD]);
                $mapping->dropdownValues()->delete();
                $this->line("  Updated mapping: {$label}");
            }
        }
    }
}
