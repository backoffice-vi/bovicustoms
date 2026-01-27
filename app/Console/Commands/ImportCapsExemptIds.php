<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsExemptIds extends Command
{
    protected $signature = 'caps:import-exempt-ids';
    protected $description = 'Import official CAPS Exempt IDs';

    public function handle()
    {
        $this->info('Importing CAPS Exempt IDs...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $exemptRaw = <<<EOT
E001 GOVERNMENT DEPARTMENTS
E002 STATUTORY BODIES
E003 CHARITABLE ORGANIZATIONS
E004 RELIGIOUS ORGANIZATIONS
E005 EDUCATIONAL INSTITUTIONS
E006 MEDICAL INSTITUTIONS
E007 DIPLOMATIC MISSIONS
E008 INTERNATIONAL ORGANIZATIONS
E009 PIONEER STATUS COMPANIES
E010 HOTEL AID ORDINANCE
E011 ENCOURAGEMENT OF INDUSTRIES
E012 RETURNING RESIDENTS
E013 PERSONAL EFFECTS
E014 HOUSEHOLD EFFECTS
E015 GIFTS (UNSOLICITED)
E016 SAMPLES (COMMERCIAL)
E017 PROMOTIONAL MATERIAL
E018 GOODS FOR REPAIR
E019 GOODS RE-IMPORTED
E020 TEMPORARY IMPORT
E021 IN TRANSIT
E022 TRANSHIPMENT
E023 BONDED WAREHOUSE
E024 DUTY FREE SHOPS
E025 CRUISE SHIP STORES
E026 AIRCRAFT STORES
E027 VESSEL STORES
E028 DISASTER RELIEF
E029 SPORTS EQUIPMENT
E030 CULTURAL GOODS
E031 SCIENTIFIC GOODS
E032 DISABLED PERSONS GOODS
E033 BLIND PERSONS GOODS
E034 GOVERNOR
E035 DEPUTY GOVERNOR
E036 PREMIER
E037 MINISTERS OF GOVERNMENT
E038 MEMBERS OF ASSEMBLY
E039 JUDGES
E040 MAGISTRATES
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_EXEMPT_ID,
            $exemptRaw,
            []
        );

        $this->info("Imported $count exempt IDs.");

        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $fields = ['Exempt ID', 'Exemption ID'];
        
        foreach ($fields as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_EXEMPT_ID]);
                $mapping->dropdownValues()->delete();
                $this->info("Updated '$label' mapping.");
            }
        }
    }
}
