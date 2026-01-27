<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsCurrencies extends Command
{
    protected $signature = 'caps:import-currencies';
    protected $description = 'Import official CAPS Currency Codes';

    public function handle()
    {
        $this->info('Importing CAPS Currencies...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $currenciesRaw = <<<EOT
AUD AUSTRALIAN DOLLAR
BBD BARBADOS DOLLAR
BMD BERMUDIAN DOLLAR
BSD BAHAMIAN DOLLAR
CAD CANADIAN DOLLAR
CHF SWISS FRANC
CNY CHINESE YUAN RENMINBI
DKK DANISH KRONE
EUR EURO
FJD FIJI DOLLAR
GBP POUND STERLING
GYD GUYANA DOLLAR
HKD HONG KONG DOLLAR
ILS ISRAELI SHEKEL
INR INDIAN RUPEE
JMD JAMAICAN DOLLAR
JPY JAPANESE YEN
KYD CAYMAN ISLANDS DOLLAR
MXN MEXICAN PESO
NOK NORWEGIAN KRONE
NZD NEW ZEALAND DOLLAR
PHP PHILIPPINE PESO
PKR PAKISTAN RUPEE
RUB RUSSIAN RUBLE
SAR SAUDI RIYAL
SEK SWEDISH KRONA
SGD SINGAPORE DOLLAR
THB THAI BAHT
TTD TRINIDAD AND TOBAGO DOLLAR
TWD NEW TAIWAN DOLLAR
USD US DOLLAR
VEF VENEZUELAN BOLIVAR
XCD EAST CARIBBEAN DOLLAR
ZAR SOUTH AFRICAN RAND
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_CURRENCY,
            $currenciesRaw,
            [],
            'USD' // Default
        );

        $this->info("Imported $count currencies.");

        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $fields = ['Currency', 'Invoice Currency'];
        
        foreach ($fields as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_CURRENCY]);
                $mapping->dropdownValues()->delete();
                $this->info("Updated '$label' mapping.");
            }
        }
    }
}
