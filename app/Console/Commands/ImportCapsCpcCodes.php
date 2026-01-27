<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsCpcCodes extends Command
{
    protected $signature = 'caps:import-cpc';
    protected $description = 'Import CAPS CPC codes';

    public function handle()
    {
        $this->info('Importing CAPS CPC codes...');

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
C400 IMPORT FOR HOME USE
C401 RETURNING BELONGERS
C402 FUNERAL FURNITURE
C403 SAMPLES
C404 SCIENTIFIC GOODS
C405 AIRCRAFT (GROUND EQUIPMENT, ETC.)
C406 EDUCATIONAL
C408 GOVERNMENT IMPORTS
C409 H.M. FORCES
C410 DIPLOMATIC AND SIMILAR ORGANIZATIONS
C411 PASSENGERS BAGGAGE ETC.
C412 UNIFORM
C413 YOUTH ORGANISATION
C414 CHARITABLE AND WELFARE GOODS
C415 STATUTORY BODIES
C416 WATER CONTAINERS
C417 BOATS AND EQUIPMENT FOR BONAFIDE FISHERMEN
C418 CHURCHES
C419 HURRICANE EQUIPMENT (SHUTTERS, FIXTURES)
C420 UNDER FISCAL INCENTIVE LEGISLATION
C421 SPECIAL CABINET OR MINISTERIAL CONCESSION
C423 FIRST TIME HOME BUILDER
C424 HOTEL AID (CAP 290)
C425 PIONEER SERVICES (CAP 297)
C426 DRUGS AND APPLIANCES
C427 COMPUTER HARDWARE AND SOFTWARE
C428 TAXI-CABS
C429 CAR SAFETY SEATS
C430 MATERIAL USED IN THE CONSTRUCTION OF HURRICANE SHU
C431 ENCOURAGEMENT OF INDUSTRIES (CAP 287)
C432 WATCHES, CLOCKS, AND ANY COMPONENT PARTS THEREOF
C433 JEWELRY MADE OF PRECIOUS METALS, STONES, OR PEARLS
C434 ARTICLES MADE OF OR FULLY PLATED WITH PRECIOUS MET
C435 PRECIOUS AND SEMI-PRECIOUS STONES, AND PEARLS
C436 CRYSTAL AND GLASSWARE
C437 CAMERAS AND ACCESSORIES
C438 HAND-HELD CALCULATORS WITH SOLID STATE CIRCUITRY
C439 ORIGINAL WORKS OF ART
C440 CHINA AND PORCELAIN
C441 EARTHENWARE, STONEWARE, AND CERAMICS
C442 TABLECLOTHS AND NAPKINS
C443 BASKETS AND BAGS OF FIBROUS VEGEATABLE MATERIALS
C444 WOOD TABLEWARE AND WOOD CARVINGS
C445 BINOCULARS AND TELESCOPES
C446 MUSIC BOXES
C447 HANDKERSHIEFS
C448 HANDBAGS, SHOES AND LUGGAGE MADE OF LEATHER
C449 ARTICLES MADE OF SHELL OR IVORY
C451 CIGARS
C452 PERFUMES
C700 TOURIST DUTY FREE SHOPPING EXEMPTION
C481 FOSSIL FUEL IMPORTS
C482 GOODS IMPORTED IN CONJUNCTION WITH IMMIGRATION
C489 ONE-OFF CUSTOMS DEPOSIT
C490 GOODS IMPORTED ON STANDING DEPOSIT
C493 GOODS PREVIOUSLY DECLARED ON STANDING DEPOSIT
C494 GOODS PREVIOUSLY DECLARED ON STANDING DEPOSIT FUEL
C495 GOODS SHORT-SHIPPED
C500 GOODS IMPORTED FOR A TEMPORARY PERIOD OF TIME (LES
C505 GOODS IMPORTED ON HIRE, FREE LOAN OR OWN USE
C603 RE-IMPORT OF GOODS (ALREADY DUTY PAID)
C625 RE-IMPORT OF REPAIRED GOODS NOT UNDER WARRANTY
C626 RE-IMPORT OF REPAIRED GOODS UNDER WARRANTY
E100 GOODS FOR EXPORTATION
E200 GOODS FOR TEMPORARY EXPORTATION
E371 RE-EXPORTATION FROM GOVERNMENT WAREHOUSE
E372 RE-EXPORTATION FROM PRIVATE WAREHOUSE
E374 RE-EXPORTATION FROM OTHER WAREHOUSE
S701 IN GOVERNMENT WAREHOUSE
S702 IN PRIVATE WAREHOUSE
S704 OTHER - (TTP) TORTOLA PIER PARK
S800 TRANSIT (FROM OFFICE OF ENTRY TO OFFICE OF EXIT)
S802 TRANS-SHIPPMENT (WITHIN PORT OR AIRPORT)
S900 SUPPLIES FOR SHIPS AND AIRCRAFT STORES
EOT;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'CPC')
            ->first();

        if ($mapping) {
            WebFormDropdownValue::where('web_form_field_mapping_id', $mapping->id)
                ->where('is_default', false)
                ->delete();

            $lines = explode("\n", $codesRaw);
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Split by first space
                $parts = explode(' ', $line, 2);
                $code = $parts[0];
                $name = $parts[1] ?? '';

                $localMatches = [$name, $code];
                
                // Common variations logic
                if ($code === 'C400') $localMatches[] = 'Home Use';
                if ($code === 'C403') $localMatches[] = 'Sample';
                if ($code === 'C500') $localMatches[] = 'Temporary';

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                    'is_default' => ($code === 'C400'), 
                ]);
            }
            $this->info("Imported " . count($lines) . " CPC codes.");
        }
    }
}
