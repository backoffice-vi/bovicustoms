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
        if (Schema::hasTable('web_form_dropdown_values')) {
            return; // Table already exists, skip creation
        }
        
        Schema::create('web_form_dropdown_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_field_mapping_id')->constrained()->onDelete('cascade');
            $table->string('option_value'); // Value attribute in the select
            $table->string('option_label'); // Display text
            $table->string('local_equivalent')->nullable(); // Maps to local data value (e.g., country code "US" -> "United States")
            $table->json('local_matches')->nullable(); // Array of local values that match this option
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['web_form_field_mapping_id', 'local_equivalent']);
            $table->index(['web_form_field_mapping_id', 'option_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_form_dropdown_values');
    }
};
