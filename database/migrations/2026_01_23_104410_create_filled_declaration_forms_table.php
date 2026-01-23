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
        Schema::create('filled_declaration_forms', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('declaration_form_id')->constrained()->onDelete('cascade');
            $table->foreignId('country_form_template_id')->constrained()->onDelete('cascade');
            
            // Tenant scope
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Selected trade contacts for this filled form
            $table->foreignId('shipper_contact_id')->nullable()->constrained('trade_contacts')->onDelete('set null');
            $table->foreignId('consignee_contact_id')->nullable()->constrained('trade_contacts')->onDelete('set null');
            $table->foreignId('broker_contact_id')->nullable()->constrained('trade_contacts')->onDelete('set null');
            $table->foreignId('notify_party_contact_id')->nullable()->constrained('trade_contacts')->onDelete('set null');
            $table->foreignId('bank_contact_id')->nullable()->constrained('trade_contacts')->onDelete('set null');
            
            // AI-extracted form field schema
            $table->json('extracted_fields')->nullable(); // Field definitions from AI analysis
            
            // Mapped and user-provided data
            $table->json('field_mappings')->nullable(); // All field values (auto + manual)
            $table->json('user_provided_data')->nullable(); // Only what user manually entered
            
            // Generated output
            $table->string('generated_file_path')->nullable();
            $table->enum('output_format', ['web', 'pdf_overlay', 'pdf_fillable', 'data_only'])->default('web');
            
            // Status
            $table->enum('status', ['draft', 'in_progress', 'complete', 'error'])->default('draft');
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Indexes (with short names to avoid MySQL identifier length limits)
            $table->index(['declaration_form_id', 'country_form_template_id'], 'filled_decl_form_template_idx');
            $table->index(['organization_id', 'status'], 'filled_decl_org_status_idx');
            $table->index(['user_id', 'status'], 'filled_decl_user_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filled_declaration_forms');
    }
};
