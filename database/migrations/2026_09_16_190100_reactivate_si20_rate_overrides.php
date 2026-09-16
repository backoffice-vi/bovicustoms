<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODES = ['1904.10', '1905.002', '3304.90', '3307.20'];

    public function up(): void
    {
        $countryId = DB::table('countries')->where('code', 'VGB')->value('id');
        if (!$countryId) {
            return;
        }

        $customsCodeIds = DB::table('customs_codes')
            ->where('country_id', $countryId)
            ->whereIn('code', self::CODES)
            ->pluck('id');

        DB::table('customs_code_rate_overrides')
            ->where('country_id', $countryId)
            ->whereIn('customs_code_id', $customsCodeIds)
            ->whereDate('effective_from', '2026-05-01')
            ->whereDate('effective_until', '2026-07-31')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $countryId = DB::table('countries')->where('code', 'VGB')->value('id');
        if (!$countryId) {
            return;
        }

        $customsCodeIds = DB::table('customs_codes')
            ->where('country_id', $countryId)
            ->whereIn('code', self::CODES)
            ->pluck('id');

        DB::table('customs_code_rate_overrides')
            ->where('country_id', $countryId)
            ->whereIn('customs_code_id', $customsCodeIds)
            ->whereDate('effective_from', '2026-05-01')
            ->whereDate('effective_until', '2026-07-31')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
