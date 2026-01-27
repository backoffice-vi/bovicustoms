<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds a column to link field mappings to country reference data instead of
     * storing dropdown values directly. When country_reference_type is set,
     * the system will look up values from country_reference_data table.
     */
    public function up(): void
    {
        Schema::table('web_form_field_mappings', function (Blueprint $table) {
            // Reference type that links to country_reference_data
            // e.g., 'carrier', 'port', 'country', 'cpc'
            // When set, dropdown values come from country_reference_data instead of web_form_dropdown_values
            $table->string('country_reference_type', 50)->nullable()->after('field_type');
            
            $table->index('country_reference_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_form_field_mappings', function (Blueprint $table) {
            $table->dropIndex(['country_reference_type']);
            $table->dropColumn('country_reference_type');
        });
    }
};
