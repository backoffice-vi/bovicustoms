<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary duty-rate overrides for specific tariff codes.
 *
 * Used when government announces a "basket of goods" with a temporary rate
 * (e.g. zero-rated essentials for a quarter). Each row replaces the duty
 * rate stored on `customs_codes.duty_rate` only for declarations whose
 * declaration_date falls in [effective_from, effective_until] for the
 * given country.
 *
 * Original rates on `customs_codes` are NEVER touched, so when a window
 * expires the system reverts automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customs_code_rate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')
                ->constrained('countries')
                ->cascadeOnDelete();
            $table->foreignId('customs_code_id')
                ->constrained('customs_codes')
                ->cascadeOnDelete();

            // Stored as a percentage to match customs_codes.duty_rate (e.g. 5.00 = 5%).
            $table->decimal('override_rate', 7, 3);

            $table->date('effective_from');
            $table->date('effective_until')->nullable();

            $table->string('source', 500)->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['country_id', 'customs_code_id', 'effective_from', 'effective_until'],
                'ccro_lookup_idx'
            );
            $table->index(['country_id', 'is_active'], 'ccro_country_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_code_rate_overrides');
    }
};
