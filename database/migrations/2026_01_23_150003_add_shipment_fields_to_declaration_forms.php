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
        Schema::table('declaration_forms', function (Blueprint $table) {
            // Link to shipment
            $table->foreignId('shipment_id')->nullable()->after('invoice_id')
                  ->constrained()->nullOnDelete();
            
            // Trade contacts
            $table->foreignId('shipper_contact_id')->nullable()->after('country_id')
                  ->constrained('trade_contacts')->nullOnDelete();
            $table->foreignId('consignee_contact_id')->nullable()->after('shipper_contact_id')
                  ->constrained('trade_contacts')->nullOnDelete();
            
            // Value calculations
            $table->decimal('fob_value', 14, 2)->nullable()->after('total_duty');
            $table->decimal('freight_total', 12, 2)->nullable()->after('fob_value');
            $table->decimal('insurance_total', 12, 2)->nullable()->after('freight_total');
            $table->decimal('cif_value', 14, 2)->nullable()->after('insurance_total');
            $table->boolean('freight_prorated')->default(false)->after('cif_value');
            $table->boolean('insurance_prorated')->default(false)->after('freight_prorated');
            
            // Duty breakdown
            $table->decimal('customs_duty_total', 12, 2)->nullable()->after('insurance_prorated');
            $table->decimal('wharfage_total', 12, 2)->nullable()->after('customs_duty_total');
            $table->decimal('other_levies_total', 12, 2)->nullable()->after('wharfage_total');
            $table->json('duty_breakdown')->nullable()->after('other_levies_total');
            $table->json('levy_breakdown')->nullable()->after('duty_breakdown');
            
            // Shipping details
            $table->string('manifest_number')->nullable()->after('levy_breakdown');
            $table->string('bill_of_lading_number')->nullable()->after('manifest_number');
            $table->string('awb_number')->nullable()->after('bill_of_lading_number');
            $table->string('carrier_name')->nullable()->after('awb_number');
            $table->string('vessel_name')->nullable()->after('carrier_name');
            $table->string('port_of_loading')->nullable()->after('vessel_name');
            $table->string('port_of_arrival')->nullable()->after('port_of_loading');
            $table->date('arrival_date')->nullable()->after('port_of_arrival');
            
            // Package details
            $table->integer('total_packages')->nullable()->after('arrival_date');
            $table->string('package_type')->nullable()->after('total_packages');
            $table->decimal('gross_weight_kg', 12, 3)->nullable()->after('package_type');
            $table->decimal('net_weight_kg', 12, 3)->nullable()->after('gross_weight_kg');
            
            // Country of origin (for goods)
            $table->string('country_of_origin', 2)->nullable()->after('net_weight_kg');
            
            // CPC Code (Customs Procedure Code)
            $table->string('cpc_code', 10)->nullable()->after('country_of_origin');
            
            // Currency
            $table->string('currency', 3)->default('USD')->after('cpc_code');
            $table->decimal('exchange_rate', 12, 6)->nullable()->after('currency');
            
            // Indexes
            $table->index('shipment_id');
            $table->index('manifest_number');
            $table->index('bill_of_lading_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropForeign(['shipper_contact_id']);
            $table->dropForeign(['consignee_contact_id']);
            
            $table->dropIndex(['shipment_id']);
            $table->dropIndex(['manifest_number']);
            $table->dropIndex(['bill_of_lading_number']);
            
            $table->dropColumn([
                'shipment_id',
                'shipper_contact_id',
                'consignee_contact_id',
                'fob_value',
                'freight_total',
                'insurance_total',
                'cif_value',
                'freight_prorated',
                'insurance_prorated',
                'customs_duty_total',
                'wharfage_total',
                'other_levies_total',
                'duty_breakdown',
                'levy_breakdown',
                'manifest_number',
                'bill_of_lading_number',
                'awb_number',
                'carrier_name',
                'vessel_name',
                'port_of_loading',
                'port_of_arrival',
                'arrival_date',
                'total_packages',
                'package_type',
                'gross_weight_kg',
                'net_weight_kg',
                'country_of_origin',
                'cpc_code',
                'currency',
                'exchange_rate',
            ]);
        });
    }
};
