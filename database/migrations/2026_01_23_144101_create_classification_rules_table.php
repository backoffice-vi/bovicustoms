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
        Schema::create('classification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('country_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name'); // Rule name for reference
            $table->enum('rule_type', ['keyword', 'category', 'override', 'instruction'])->default('keyword');
            // keyword = if item contains keyword, suggest code
            // category = all items in category X use code Y
            // override = always use this code for exact match
            // instruction = general instruction for AI
            $table->text('condition'); // The condition/keyword/pattern to match
            $table->string('target_code')->nullable(); // The HS code to assign (null for instructions)
            $table->text('instruction')->nullable(); // Additional instruction for the AI
            $table->integer('priority')->default(0); // Higher priority rules are applied first
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['organization_id', 'is_active']);
            $table->index(['country_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classification_rules');
    }
};
