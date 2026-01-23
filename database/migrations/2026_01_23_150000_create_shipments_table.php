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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            
            // Status tracking
            $table->enum('status', ['draft', 'documents_uploaded', 'declaration_generated', 'released'])
                  ->default('draft');
            
            // Trade contact references
            $table->foreignId('shipper_contact_id')->nullable()->constrained('trade_contacts')->nullOnDelete();
            $table->foreignId('consignee_contact_id')->nullable()->constrained('trade_contacts')->nullOnDelete();
            $table->foreignId('notify_party_contact_id')->nullable()->constrained('trade_contacts')->nullOnDelete();
            
            // Aggregated financial totals
            $table->decimal('fob_total', 14, 2)->default(0);
            $table->decimal('freight_total', 14, 2)->default(0);
            $table->decimal('insurance_total', 14, 2)->default(0);
            $table->decimal('cif_total', 14, 2)->default(0);
            
            // Insurance settings
            $table->enum('insurance_method', ['manual', 'percentage', 'document'])->default('manual');
            $table->decimal('insurance_percentage', 5, 2)->nullable(); // e.g., 0.50 for 0.5%
            
            // Shipping details (from B/L or AWB)
            $table->string('bill_of_lading_number')->nullable();
            $table->string('awb_number')->nullable();
            $table->string('manifest_number')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('final_destination')->nullable();
            $table->date('estimated_arrival_date')->nullable();
            $table->date('actual_arrival_date')->nullable();
            
            // Package details
            $table->integer('total_packages')->nullable();
            $table->string('package_type')->nullable(); // boxes, pallets, containers, etc.
            $table->decimal('gross_weight_kg', 12, 3)->nullable();
            $table->decimal('net_weight_kg', 12, 3)->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('organization_id');
            $table->index('user_id');
            $table->index('bill_of_lading_number');
            $table->index('manifest_number');
        });

        // Pivot table for shipment-invoice many-to-many relationship
        Schema::create('shipment_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            
            // Prorated values for this invoice within the shipment
            $table->decimal('prorated_freight', 12, 2)->nullable();
            $table->decimal('prorated_insurance', 12, 2)->nullable();
            $table->decimal('invoice_fob', 12, 2)->nullable();
            
            $table->timestamps();
            
            // Ensure unique combinations
            $table->unique(['shipment_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_invoices');
        Schema::dropIfExists('shipments');
    }
};
