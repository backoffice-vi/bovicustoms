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
        Schema::create('web_form_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_page_id')->constrained()->onDelete('cascade');
            
            // Local data source
            $table->string('local_field')->nullable(); // e.g., "declaration.vessel_name" or "shipper.company_name"
            $table->string('local_table')->nullable(); // declaration_forms, shipments, trade_contacts, invoices
            $table->string('local_column')->nullable(); // Column name in local table
            $table->string('local_relation')->nullable(); // e.g., "shipperContact" for related models
            
            // Web form target
            $table->string('web_field_label'); // Label shown on the web form
            $table->string('web_field_name')->nullable(); // name attribute of the field
            $table->string('web_field_id')->nullable(); // id attribute of the field
            $table->json('web_field_selectors'); // Array of CSS selectors to try (in order)
            $table->enum('field_type', ['text', 'select', 'checkbox', 'radio', 'date', 'textarea', 'hidden', 'number'])->default('text');
            
            // Options and transformations
            $table->json('options')->nullable(); // For dropdowns: [{value: 'US', label: 'United States'}]
            $table->json('value_transform')->nullable(); // Transformation rules: {type: 'date_format', format: 'm/d/Y'}
            $table->string('default_value')->nullable(); // Default value if local data is empty
            $table->string('static_value')->nullable(); // Always use this value (overrides local)
            
            // Validation
            $table->boolean('is_required')->default(false);
            $table->integer('max_length')->nullable();
            $table->string('validation_pattern')->nullable(); // Regex pattern
            
            // UI hints
            $table->integer('tab_order')->nullable(); // Order for filling fields
            $table->string('section')->nullable(); // Section name for grouping
            $table->text('notes')->nullable(); // Admin notes
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['web_form_page_id', 'tab_order']);
            $table->index(['local_table', 'local_column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_form_field_mappings');
    }
};
