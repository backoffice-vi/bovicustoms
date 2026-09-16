<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_levies', function (Blueprint $table) {
            $table->dropUnique(['country_id', 'levy_code']);
            $table->index(
                ['country_id', 'levy_code', 'effective_from', 'effective_until'],
                'country_levies_effective_lookup'
            );
        });

        $countryId = DB::table('countries')->where('code', 'VGB')->value('id');

        if (!$countryId) {
            return;
        }

        DB::table('country_levies')->updateOrInsert(
            [
                'country_id' => $countryId,
                'levy_code' => 'WHA',
                'effective_from' => '2026-05-01',
                'effective_until' => '2026-07-31',
            ],
            [
                'levy_name' => 'Wharfage',
                'description' => 'Temporary BVI cost-of-living relief wharfage rate.',
                'rate' => 1.0000,
                'rate_type' => 'percentage',
                'unit' => null,
                'calculation_basis' => 'fob',
                'applies_to_all_tariffs' => true,
                'applicable_tariff_chapters' => null,
                'exempt_tariff_codes' => null,
                'exempt_organization_types' => null,
                'display_order' => 0,
                'is_active' => true,
                'legal_reference' => 'British Virgin Islands Ports Authority (Amendment) Regulations, 2026',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $countryId = DB::table('countries')->where('code', 'VGB')->value('id');

        if ($countryId) {
            DB::table('country_levies')
                ->where('country_id', $countryId)
                ->where('levy_code', 'WHA')
                ->whereDate('effective_from', '2026-05-01')
                ->whereDate('effective_until', '2026-07-31')
                ->delete();
        }

        Schema::table('country_levies', function (Blueprint $table) {
            $table->dropIndex('country_levies_effective_lookup');
            $table->unique(['country_id', 'levy_code']);
        });
    }
};
