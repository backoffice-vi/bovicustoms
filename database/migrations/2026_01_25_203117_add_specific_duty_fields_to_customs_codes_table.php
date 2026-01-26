<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customs_codes', function (Blueprint $table) {
            // Duty type: 'ad_valorem' (percentage), 'specific' (fixed amount), 'compound' (both)
            $table->string('duty_type', 20)->default('ad_valorem')->after('duty_rate');
            
            // Specific duty amount (fixed dollar amount per unit)
            $table->decimal('specific_duty_amount', 10, 2)->nullable()->after('duty_type');
            
            // What the specific duty is per (e.g., "per animal", "per bird", "per kg", "per gallon")
            $table->string('specific_duty_unit', 50)->nullable()->after('specific_duty_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->dropColumn(['duty_type', 'specific_duty_amount', 'specific_duty_unit']);
        });
    }
};
