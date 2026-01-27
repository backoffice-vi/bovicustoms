<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds customs registration ID field for importers and declarants/brokers
     * - For importers: This is their customs importer registration number
     * - For brokers: This is their customs broker/declarant license number
     */
    public function up(): void
    {
        Schema::table('trade_contacts', function (Blueprint $table) {
            // Customs registration ID (Importer ID for consignees, Declarant ID for brokers)
            $table->string('customs_registration_id')->nullable()->after('license_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trade_contacts', function (Blueprint $table) {
            $table->dropColumn('customs_registration_id');
        });
    }
};
