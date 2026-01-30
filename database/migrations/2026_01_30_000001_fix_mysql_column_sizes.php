<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix column sizes for MySQL compatibility.
     * SQLite doesn't enforce VARCHAR limits but MySQL does.
     */
    public function up(): void
    {
        // Only apply for MySQL
        if (config('database.default') !== 'mysql') {
            return;
        }

        // Tariff chapters - title can be very long
        Schema::table('tariff_chapters', function (Blueprint $table) {
            $table->text('title')->change();
        });

        // Tariff sections - title can be long
        Schema::table('tariff_sections', function (Blueprint $table) {
            $table->text('title')->change();
        });

        // Customs codes - description can be very long
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->text('description')->change();
        });

        // Classification exclusions
        if (Schema::hasTable('classification_exclusions')) {
            Schema::table('classification_exclusions', function (Blueprint $table) {
                $table->text('rule_text')->change();
            });
        }

        // Exemption categories
        if (Schema::hasTable('exemption_categories')) {
            Schema::table('exemption_categories', function (Blueprint $table) {
                $table->text('name')->change();
                $table->text('description')->nullable()->change();
            });
        }

        // Exemption conditions
        if (Schema::hasTable('exemption_conditions')) {
            Schema::table('exemption_conditions', function (Blueprint $table) {
                $table->text('description')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversing - TEXT columns work fine
    }
};
