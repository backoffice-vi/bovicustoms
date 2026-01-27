<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;

class UpdateCapsFieldTypes extends Command
{
    protected $signature = 'caps:update-types';
    protected $description = 'Update CAPS field types to select for lookup fields';

    public function handle()
    {
        $this->info('Updating CAPS field types...');

        $target = WebFormTarget::where('code', 'caps_bvi')->first();
        if (!$target) {
            $this->error('CAPS target not found.');
            return 1;
        }

        $page = WebFormPage::where('web_form_target_id', $target->id)
            ->where('name', 'TD Data Entry')
            ->first();

        if (!$page) {
            $this->error('TD Data Entry page not found.');
            return 1;
        }

        $fieldsToUpdate = [
            'Carrier ID',
            'Port of Arrival',
            'Supplier Country',
            'Country of Direct Shipment',
            'Country of Original Shipment',
            'Method of Payment',
            'Country of Origin (Item)',
            'Type of Packages',
            'Units',
            'Currency',
            'Freight Charge Code',
            'Insurance Charge Code',
            'Tax Type 1 (Customs Duty)',
            'Tax Type 2 (Wharfage)',
            'Tax Exempt Ind. 1',
            'CPC',
            'Additional Info Code',
        ];

        foreach ($fieldsToUpdate as $label) {
            $mapping = WebFormFieldMapping::where('web_form_page_id', $page->id)
                ->where('web_field_label', $label)
                ->first();

            if ($mapping) {
                $mapping->update(['field_type' => 'select']);
                $this->info("Updated {$label} to select");
            } else {
                $this->warn("Mapping not found: {$label}");
            }
        }

        $this->info('Field type updates complete.');
        return 0;
    }
}
