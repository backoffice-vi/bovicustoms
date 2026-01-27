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
        Schema::create('web_form_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "CAPS System"
            $table->string('code')->unique(); // e.g., "caps_bvi"
            $table->string('base_url'); // e.g., "https://caps.gov.vg"
            $table->string('login_url'); // e.g., "/CAPSWeb/"
            $table->text('credentials')->nullable(); // Encrypted JSON: {username_field, password_field, username, password}
            $table->enum('auth_type', ['form', 'oauth', 'api_key', 'none'])->default('form');
            $table->json('workflow_steps')->nullable(); // Ordered steps to complete submission
            $table->json('config')->nullable(); // Additional configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_ai')->default(true); // Whether to use AI-assisted submission
            $table->timestamp('last_mapped_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['country_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_form_targets');
    }
};
