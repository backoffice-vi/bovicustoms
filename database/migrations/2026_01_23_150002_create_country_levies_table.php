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
        Schema::create('country_levies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            
            // Levy identification
            $table->string('levy_code', 20); // e.g., WHA, CUD, ENV, etc.
            $table->string('levy_name'); // e.g., "Wharfage", "Customs Duty", "Environmental Levy"
            $table->text('description')->nullable();
            
            // Rate configuration
            $table->decimal('rate', 10, 4); // e.g., 2.0000 for 2%
            $table->enum('rate_type', ['percentage', 'fixed_amount', 'per_unit'])->default('percentage');
            $table->string('unit')->nullable(); // for per_unit rate type (e.g., "kg", "item")
            
            // Calculation basis
            $table->enum('calculation_basis', ['fob', 'cif', 'duty', 'quantity', 'weight'])
                  ->default('fob');
            
            // Application rules
            $table->boolean('applies_to_all_tariffs')->default(true);
            $table->json('applicable_tariff_chapters')->nullable(); // If not all, which chapters
            $table->json('exempt_tariff_codes')->nullable(); // Specific codes exempt from this levy
            
            // Organization exemptions
            $table->json('exempt_organization_types')->nullable(); // e.g., ["government", "non_profit"]
            
            // Order and status
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            // Legal reference
            $table->string('legal_reference')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('country_id');
            $table->index('levy_code');
            $table->index('is_active');
            $table->unique(['country_id', 'levy_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_levies');
    }
};
