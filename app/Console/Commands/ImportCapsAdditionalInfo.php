<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsAdditionalInfo extends Command
{
    protected $signature = 'caps:import-additional-info';
    protected $description = 'Import official CAPS Additional Info Codes';

    public function handle()
    {
        $this->info('Importing CAPS Additional Info Codes...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $infoRaw = <<<EOT
LIC IMPORT LICENSE REQUIRED
PER IMPORT PERMIT REQUIRED
CER CERTIFICATE OF ORIGIN REQUIRED
PHY PHYTOSANITARY CERTIFICATE REQUIRED
VET VETERINARY CERTIFICATE REQUIRED
HEA HEALTH CERTIFICATE REQUIRED
INS INSURANCE CERTIFICATE REQUIRED
INV COMMERCIAL INVOICE REQUIRED
BOL BILL OF LADING REQUIRED
AWB AIR WAYBILL REQUIRED
PAC PACKING LIST REQUIRED
VAL VALUATION REPORT REQUIRED
POL POLICE REPORT REQUIRED
INS INSPECTION REPORT REQUIRED
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_ADDITIONAL_INFO,
            $infoRaw,
            []
        );

        $this->info("Imported $count additional info codes.");

        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $fields = ['Additional Info', 'Additional Information'];
        
        foreach ($fields as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_ADDITIONAL_INFO]);
                $mapping->dropdownValues()->delete();
                $this->info("Updated '$label' mapping.");
            }
        }
    }
}
