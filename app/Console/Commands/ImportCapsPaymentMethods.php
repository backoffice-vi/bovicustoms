<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormDropdownValue;

class ImportCapsPaymentMethods extends Command
{
    protected $signature = 'caps:import-payment-methods';
    protected $description = 'Import CAPS Payment Method codes';

    public function handle()
    {
        $this->info('Importing CAPS Payment Method codes...');

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
10 CASH / TC - BVI
11 Cash / TC - USD
12 Cash / TC - CAD
13 Cash / TC - GBP
14 Cash / TC - EUR
20 CHEQUE - BVI
21 BANK DRAFT - BVI
22 Cheque - USD
23 Bank Draft - USD
40 VISA
41 MasterCard
42 AMEX
60 Easylink
61 Bank Plus
93 OGD
BW BANK WIRE
CD Credit / Debit Card
CQ Cheque
CS Cash
CTY CRYPTOCURRENCY
MON MONEYGRAM
TA Trader Account
WES WESTERN UNION
EOT;

        $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
            ->where('web_field_label', 'Method of Payment')
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
                
                // Common variations logic
                if ($code === 'CS' || $code === '10' || $code === '11') $localMatches[] = 'Cash';
                if ($code === 'CQ' || $code === '20' || $code === '22') $localMatches[] = 'Cheque';
                if ($code === 'CD' || $code === '40' || $code === '41' || $code === '42') $localMatches[] = 'Credit Card';
                if ($code === 'TA') $localMatches = array_merge($localMatches, ['Account', 'On Account']);

                WebFormDropdownValue::create([
                    'web_form_field_mapping_id' => $mapping->id,
                    'option_value' => $code,
                    'option_label' => "$code - $name",
                    'local_matches' => $localMatches,
                    'sort_order' => $index,
                    // Defaulting to Trader Account as it's common for brokers, but let's stick to Cash as safe default if not specified elsewhere
                    'is_default' => ($code === 'TA'), 
                ]);
            }
            $this->info("Imported " . count($lines) . " payment method codes.");
        } else {
            $this->error("Mapping for 'Method of Payment' not found.");
        }
    }
}
