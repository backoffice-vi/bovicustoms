<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsCurrencies extends Command
{
    protected $signature = 'caps:import-currencies';
    protected $description = 'Import CAPS Currency codes';

    public function handle()
    {
        $this->info('Importing CAPS Currency codes...');

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
ATS Austria Schilling
AUD Australia Dollar
BEF Belgium
BMD Bermuda Dollar
CAD Canada Dollar
CHF Switzerland Franc
CNY CHINESE YUAN
DEM Germany Deutche Mark
DKK Denmark Krona
DOP DOMINICAN PESO
ESP Spain Peseta
EUR Euro
FRF France Franc
GBP United Kingdom Pound
HKD Hong Kong Dollar
IEP Ireland Punt
INR INDIAN RUPEE
ITL Italy Lira
JMD Jamaica Dollar
JPY Japan Yen
KKD KKD
MXN MEXICAN PESO
NLG Holland Guilder
NOK Norway Krona
NZD New Zealand Dollar
PTE Potugal Escudo
SEK Sweden Krona
SGD Singapore Dollar
TTD TRINIDADIAN DOLLAR
USD US Dollar
XCD EAST CARIBBEAN DOLLAR
ZAR South African Rand
EOT;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Currency')
            ->first();

        if ($mapping) {
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
                
                // Common symbol mapping
                if ($code === 'USD') $localMatches = array_merge($localMatches, ['$', 'US$']);
                if ($code === 'EUR') $localMatches = array_merge($localMatches, ['€', 'Euro']);
                if ($code === 'GBP') $localMatches = array_merge($localMatches, ['£', 'Pound', 'Sterling']);
                if ($code === 'CAD') $localMatches[] = 'C$';
                if ($code === 'AUD') $localMatches[] = 'A$';
                if ($code === 'JPY') $localMatches[] = '¥';

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                    'is_default' => ($code === 'USD'), 
                ]);
            }
            $this->info("Imported " . count($lines) . " currency codes.");
        }
    }
}
