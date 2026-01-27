<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsUnits extends Command
{
    protected $signature = 'caps:import-units';
    protected $description = 'Import official CAPS Unit Codes';

    public function handle()
    {
        $this->info('Importing CAPS Units...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $unitsRaw = <<<EOT
2U 2 Units
BAG Bag
BAR Barrel
BDL Bundle
BKT Bucket
BOX Box
BRL Barrel
CAN Can
CRT Crate
CTN Carton
CYL Cylinder
DZN Dozen
GAL Gallon
GRS Gross
HDS Heads
KG Kilogram
LIT Liter
LBS Pounds
M Meter
M2 Square Meter
M3 Cubic Meter
NO Number
OZ Ounce
PAC Pack
PAL Pallet
PCS Pieces
PRS Pairs
ROL Roll
SET Set
SHT Sheet
TON Ton
UNT Unit
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_UNIT,
            $unitsRaw,
            [],
            'NO' // Default
        );

        $this->info("Imported $count units.");

        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $fields = ['Unit', 'Unit of Measure', 'Quantity Unit', 'Gross Mass Unit', 'Net Mass Unit'];
        
        foreach ($fields as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_UNIT]);
                $mapping->dropdownValues()->delete();
                $this->info("Updated '$label' mapping.");
            }
        }
    }
}
