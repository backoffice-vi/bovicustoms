<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Country-level duty calculation policy.
 *
 * Each row says "for declarations on country X with declaration_date in
 * [effective_from, effective_until], compute customs duty on `basis`".
 *
 * BVI emergency cost-of-living measure (May–July 2026) shifted the basis
 * from CIF to FOB. This table lets us encode that without code changes
 * and have it auto-revert when the window expires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_duty_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')
                ->constrained('countries')
                ->cascadeOnDelete();

            // Customs duty calculation basis: 'fob' or 'cif'.
            // Wharfage and other levies still use their own per-row basis on country_levies.
            $table->string('basis', 8);

            $table->date('effective_from');
            // Null = open-ended. Inclusive when set.
            $table->date('effective_until')->nullable();

            // Provenance — paste the gov.vg URL or law citation here.
            $table->string('source', 500)->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['country_id', 'effective_from', 'effective_until'], 'cdp_country_window_idx');
            $table->index(['country_id', 'is_active'], 'cdp_country_active_idx');
        });

        // Seed the BVI 2026 cost-of-living measure if BVI exists.
        $bviId = DB::table('countries')->where('code', 'VGB')->value('id');
        if ($bviId) {
            DB::table('country_duty_policies')->insert([
                'country_id' => $bviId,
                'basis' => 'fob',
                'effective_from' => '2026-05-01',
                'effective_until' => '2026-07-31',
                'source' => 'https://gov.vg/news/government-lowering-import-duties',
                'notes' => 'Government to Lower Import Duties to Reduce the Cost of Living. '
                    . 'Effective May 2026 for three months, through July 2026. '
                    . 'Removal of duties on insurance and freight; shift from CIF to FOB.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('country_duty_policies');
    }
};
