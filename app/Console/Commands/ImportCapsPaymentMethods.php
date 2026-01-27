<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\CountryReferenceData;

class ImportCapsPaymentMethods extends Command
{
    protected $signature = 'caps:import-payment-methods';
    protected $description = 'Import official CAPS Payment Methods';

    public function handle()
    {
        $this->info('Importing CAPS Payment Methods...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found. Run caps:setup first.');
            return 1;
        }

        $methodsRaw = <<<EOT
CSH CASH
CHQ CHEQUE
CRC CREDIT CARD
DBC DEBIT CARD
DFT BANK DRAFT
WTR WIRE TRANSFER
ONL ONLINE PAYMENT
ACC ACCOUNT DEBIT
PRE PREPAYMENT
EOT;

        $count = CountryReferenceData::importFromText(
            $target->country_id,
            CountryReferenceData::TYPE_PAYMENT_METHOD,
            $methodsRaw,
            [],
            'CSH' // Default
        );

        $this->info("Imported $count payment methods.");

        $this->updateMappings($target);

        return 0;
    }

    protected function updateMappings($target)
    {
        $page = $target->pages()->where('name', 'TD Data Entry')->first();
        if (!$page) return;

        $fields = ['Payment Method', 'Method of Payment'];
        
        foreach ($fields as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['country_reference_type' => CountryReferenceData::TYPE_PAYMENT_METHOD]);
                $mapping->dropdownValues()->delete();
                $this->info("Updated '$label' mapping.");
            }
        }
    }
}
