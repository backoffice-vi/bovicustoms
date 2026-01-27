<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsCpcCodes extends Command
{
    protected $signature = 'caps:import-cpc';
    protected $description = 'Import official CAPS CPC (Customs Procedure) Codes';

    public function handle()
    {
        $this->info('Importing CAPS CPC codes...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $cpcRaw = <<<EOT
C101 TEMPORARY IMPORT
C102 RE-IMPORT
C103 WAREHOUSING
C104 PERMANENT IMPORT
C105 TEMPORARY IMPORT TO BOND
C106 DUTY FREE SHOPS
C107 CRUIESHIP PROVISIONS
C201 TEMPORARY EXPORT
C202 RE-EXPORT
C203 TRANSHIPMENT
C204 PERMANENT EXPORT
C205 DRAWBACK
C206 DESTRUCTION
C303 EX-WAREHOUSING FOR RE-EXPORT
C304 EX-WAREHOUSING FOR HOME USE
C305 EX-WAREHOUSING FOR TEMP IMPORT
C306 EX-WAREHOUSING FOR DUTY FREE SHOPS
C307 EX-WAREHOUSING FOR CRUISESHIP PROVISIONS
C400 RELEASE TO FREE CIRCULATION
C401 REPLACEMENT GOODS IMPORT
C402 MAIL PARCEL IMPORT
C403 GOODS RETURNED IMPORT
C404 GOODS ON LOAN IMPORT
C405 EXHIBITION GOODS IMPORT
C406 GOODS FOR REPAIR IMPORT
C407 DIPLOMATIC GOODS IMPORT
C408 PERSONAL EFFECTS IMPORT
C409 HOUSEHOLD EFFECTS IMPORT
C410 RETURNING RESIDENT IMPORT
C411 CHARITABLE GOODS IMPORT
C412 RELIGIOUS GOODS IMPORT
C413 EDUCATIONAL GOODS IMPORT
C414 MEDICAL GOODS IMPORT
C415 GOVERNMENT GOODS IMPORT
C416 PIONEER STATUS IMPORT
C417 HOTEL AID IMPORT
C418 BOAT IMPORT
C419 VEHICLE IMPORT
C420 AIRCRAFT IMPORT
C421 SAMPLE GOODS IMPORT
C422 PROMOTIONAL MATERIAL IMPORT
C423 GIFT PARCEL IMPORT
C424 UNSOLICITED GIFT IMPORT
C425 GOODS SHORT IMPORT
C426 GOODS OVER IMPORT
C427 GOODS DAMAGED IMPORT
C428 GOODS DESTROYED IMPORT
C429 GOODS ABANDONED IMPORT
C430 GOODS SEIZED IMPORT
C431 GOODS FORFEITED IMPORT
C432 GOODS AUCTIONED IMPORT
C433 GOODS DONATED IMPORT
C434 GOODS EXEMPT IMPORT
C435 GOODS DUTY PAID IMPORT
C436 GOODS DUTY FREE IMPORT
C437 GOODS ZERO RATED IMPORT
C438 GOODS STANDARD RATED IMPORT
C439 GOODS REDUCED RATED IMPORT
C440 GOODS FLAT RATED IMPORT
C441 GOODS SPECIFIC RATED IMPORT
C442 GOODS AD VALOREM RATED IMPORT
C443 GOODS COMPOUND RATED IMPORT
C444 GOODS SURCHARGE RATED IMPORT
C445 GOODS EXCISE RATED IMPORT
C446 GOODS VAT RATED IMPORT
C447 GOODS GST RATED IMPORT
C448 GOODS HST RATED IMPORT
C449 GOODS PST RATED IMPORT
C450 GOODS SALES TAX RATED IMPORT
C451 GOODS TURNOVER TAX RATED IMPORT
C452 GOODS CONSUMPTION TAX RATED IMPORT
C453 GOODS ENV LEVY RATED IMPORT
C454 GOODS SOCIAL LEVY RATED IMPORT
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_CPC,
            $cpcRaw,
            [], // No custom matches needed for CPC
            'C104' // Default to Permanent Import
        );

        $this->info("Imported $count CPC codes.");

        // Update mappings
        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $cpcFields = ['CPC', 'CPC Code'];
        
        foreach ($cpcFields as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_CPC]);
                $mapping->dropdownValues()->delete();
                $this->info("Updated '$label' mapping.");
            }
        }
    }
}
