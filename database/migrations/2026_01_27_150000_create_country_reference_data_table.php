<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores country-specific reference data (carriers, ports, CPC codes, etc.)
     * independent of web form configurations. This ensures the data persists even if
     * web form targets are deleted.
     */
    public function up(): void
    {
        Schema::create('country_reference_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            
            // Reference type categorization
            $table->string('reference_type', 50); // 'carrier', 'port', 'cpc', 'currency', 'unit', 'tax_type', 'exempt_id', 'charge_code', 'additional_info', 'country', 'payment_method'
            
            // The official code used by the country's customs system
            $table->string('code', 50);
            
            // Display label/name
            $table->string('label');
            
            // Array of local values that map to this code
            // e.g., ["USA", "United States of America", "US"] all map to code "US"
            $table->json('local_matches')->nullable();
            
            // Additional metadata specific to this reference type
            // e.g., for carriers: {"airline": true}, for ports: {"type": "sea"}
            $table->json('metadata')->nullable();
            
            // Administrative fields
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Unique constraint: one code per type per country
            $table->unique(['country_id', 'reference_type', 'code'], 'country_ref_unique');
            
            // Indexes for common lookups
            $table->index(['country_id', 'reference_type', 'is_active'], 'country_ref_type_active');
            $table->index(['country_id', 'reference_type', 'is_default'], 'country_ref_type_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_reference_data');
    }
};
