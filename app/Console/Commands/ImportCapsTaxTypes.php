<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsTaxTypes extends Command
{
    protected $signature = 'caps:import-tax-types';
    protected $description = 'Import official CAPS Tax Types';

    public function handle()
    {
        $this->info('Importing CAPS Tax Types...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $taxRaw = <<<EOT
DTY IMPORT DUTY
WFG WHARFAGE
SRG SURCHARGE
ENV ENVIRONMENTAL LEVY
EFL ENVIRONMENTAL FUEL LEVY
CSL CRUISE SHIP LEVY
DEP DEPARTURE TAX
CON CONSUMPTION TAX
EXC EXCISE TAX
HOT HOTEL ACCOMMODATION TAX
STA STAMP DUTY
LIC LICENSE FEE
PER PERMIT FEE
INS INSPECTION FEE
STO STORAGE FEE
PRO PROCESSING FEE
MIS MISCELLANEOUS FEE
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_TAX_TYPE,
            $taxRaw,
            [],
            'DTY' // Default
        );

        $this->info("Imported $count tax types.");

        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Tax Type')
            ->first();

        if ($mapping) {
            $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_TAX_TYPE]);
            $mapping->dropdownValues()->delete();
            $this->info("Updated 'Tax Type' mapping.");
        }
    }
}
