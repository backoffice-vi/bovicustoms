<?php

namespace App\Console\Commands;

use App\Models\CountryReferenceData;
use Illuminate\Console\Command;

class CleanupBogusPaymentMethods extends Command
{
    protected $signature = 'caps:cleanup-bogus-payment-methods
        {--country-id=1 : Country to clean}
        {--apply : Actually deactivate (otherwise dry-run)}';

    protected $description = 'Deactivate the 9 bogus 3-letter payment-method codes (ACC, CHQ, CRC, CSH, DBC, DFT, ONL, PRE, WTR) that were never real CAPS codes';

    private const BOGUS_CODES = ['ACC', 'CHQ', 'CRC', 'CSH', 'DBC', 'DFT', 'ONL', 'PRE', 'WTR'];

    public function handle(): int
    {
        $countryId = (int) $this->option('country-id');
        $apply = (bool) $this->option('apply');

        $rows = CountryReferenceData::where('country_id', $countryId)
            ->where('reference_type', CountryReferenceData::TYPE_PAYMENT_METHOD)
            ->whereIn('code', self::BOGUS_CODES)
            ->get();

        $this->info("Found " . $rows->count() . " bogus payment methods to deactivate (country_id={$countryId}):");
        foreach ($rows as $r) {
            $state = $r->is_active ? 'ACTIVE' : 'inactive';
            $default = $r->is_default ? '[DEFAULT]' : '';
            $this->line(sprintf("  %-5s %-30s %s %s", $r->code, $r->label, $state, $default));
        }

        if (!$apply) {
            $this->newLine();
            $this->warn("DRY-RUN ONLY. Re-run with --apply to deactivate.");
            return self::SUCCESS;
        }

        $now = now();
        $note = "Not in HMC v3 alpha 2024; deactivated {$now->toDateString()}";

        foreach ($rows as $r) {
            $r->is_active = false;
            $r->is_default = false;
            $existingNote = (string) ($r->notes ?? '');
            if (!str_contains($existingNote, 'HMC v3 alpha')) {
                $r->notes = trim(($existingNote ? $existingNote . ' | ' : '') . $note);
            }
            $r->save();
        }

        $this->info(PHP_EOL . "Deactivated {$rows->count()} bogus payment methods.");

        $defaults = CountryReferenceData::where('country_id', $countryId)
            ->where('reference_type', CountryReferenceData::TYPE_PAYMENT_METHOD)
            ->where('is_default', true)
            ->get(['code', 'label']);
        $this->info('Active default(s):');
        foreach ($defaults as $d) {
            $this->line("  {$d->code} = {$d->label}");
        }

        return self::SUCCESS;
    }
}
