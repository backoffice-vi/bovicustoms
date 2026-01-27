<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds fields needed for customs declaration forms:
     * - Container ID: From B/L (may be empty for LCL shipments)
     * - Shipping origin details: City, country of direct shipment, country of origin
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Container ID from Bill of Lading (nullable for LCL shipments)
            $table->string('container_id')->nullable()->after('voyage_number');
            
            // Shipping origin details for declaration
            $table->string('city_of_direct_shipment')->nullable()->after('port_of_loading');
            $table->foreignId('country_of_direct_shipment_id')->nullable()->after('city_of_direct_shipment')
                ->constrained('countries')->nullOnDelete();
            $table->foreignId('country_of_origin_id')->nullable()->after('country_of_direct_shipment_id')
                ->constrained('countries')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['country_of_direct_shipment_id']);
            $table->dropForeign(['country_of_origin_id']);
            $table->dropColumn([
                'container_id',
                'city_of_direct_shipment',
                'country_of_direct_shipment_id',
                'country_of_origin_id',
            ]);
        });
    }
};
