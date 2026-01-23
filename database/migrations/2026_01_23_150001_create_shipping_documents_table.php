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
        Schema::create('shipping_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Document type and identification
            $table->enum('document_type', [
                'bill_of_lading',
                'air_waybill',
                'packing_list',
                'certificate_of_origin',
                'insurance_certificate',
                'commercial_invoice',
                'other'
            ])->default('bill_of_lading');
            
            $table->string('document_number')->nullable(); // B/L number, AWB number, etc.
            $table->string('manifest_number')->nullable();
            
            // File storage
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedBigInteger('file_size');
            
            // Extraction status
            $table->enum('extraction_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending');
            $table->timestamp('extracted_at')->nullable();
            $table->text('extraction_error')->nullable();
            
            // Extracted shipping details
            $table->string('carrier_name')->nullable();
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('final_destination')->nullable();
            $table->date('shipping_date')->nullable();
            $table->date('estimated_arrival')->nullable();
            
            // Extracted party details (raw from document)
            $table->json('shipper_details')->nullable();
            $table->json('consignee_details')->nullable();
            $table->json('notify_party_details')->nullable();
            $table->json('forwarding_agent_details')->nullable();
            
            // Extracted financial details
            $table->decimal('freight_charges', 12, 2)->nullable();
            $table->string('freight_terms')->nullable(); // prepaid, collect, etc.
            $table->decimal('insurance_amount', 12, 2)->nullable();
            $table->decimal('other_charges', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            
            // Extracted cargo details
            $table->integer('total_packages')->nullable();
            $table->string('package_type')->nullable();
            $table->text('goods_description')->nullable();
            $table->decimal('gross_weight_kg', 12, 3)->nullable();
            $table->decimal('net_weight_kg', 12, 3)->nullable();
            $table->decimal('volume_cbm', 12, 3)->nullable();
            
            // Invoice references extracted from document
            $table->json('invoice_references')->nullable();
            
            // Full extracted data and text
            $table->json('extracted_data')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->json('extraction_meta')->nullable();
            
            // Verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->index('shipment_id');
            $table->index('document_type');
            $table->index('document_number');
            $table->index('extraction_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_documents');
    }
};
