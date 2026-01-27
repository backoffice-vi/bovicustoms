<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsChargeCodes extends Command
{
    protected $signature = 'caps:import-charge-codes';
    protected $description = 'Import official CAPS Charge Codes';

    public function handle()
    {
        $this->info('Importing CAPS Charge Codes...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $chargesRaw = <<<EOT
FRT FREIGHT CHARGES
INS INSURANCE CHARGES
OTH OTHER CHARGES
HND HANDLING CHARGES
PKG PACKAGING CHARGES
DOC DOCUMENTATION CHARGES
TRN TRANSPORT CHARGES
STR STORAGE CHARGES
DEM DEMURRAGE CHARGES
WHF WHARFAGE CHARGES
LIT LIGHTERAGE CHARGES
AGE AGENCY FEES
COM COMMISSION FEES
BRO BROKERAGE FEES
BNK BANK CHARGES
INT INTEREST CHARGES
DIS DISCOUNTS allowed
REB REBATES allowed
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_CHARGE_CODE,
            $chargesRaw,
            [],
            'FRT' // Default
        );

        $this->info("Imported $count charge codes.");

        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Charge Code')
            ->first();

        if ($mapping) {
            $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_CHARGE_CODE]);
            $mapping->dropdownValues()->delete();
            $this->info("Updated 'Charge Code' mapping.");
        }
    }
}
