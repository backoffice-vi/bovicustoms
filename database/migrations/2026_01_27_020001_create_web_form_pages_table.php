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
        Schema::create('web_form_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_target_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Trade Declaration Entry"
            $table->string('url_pattern'); // e.g., "/TDDataEntryServlet"
            $table->integer('sequence_order')->default(1); // Order in workflow
            $table->enum('page_type', ['login', 'form', 'confirmation', 'search', 'other'])->default('form');
            $table->json('page_snapshot')->nullable(); // Cached accessibility snapshot
            $table->json('navigation')->nullable(); // How to reach this page from previous
            $table->string('submit_selector')->nullable(); // CSS selector for submit button
            $table->string('success_indicator')->nullable(); // Selector or text to verify success
            $table->string('error_indicator')->nullable(); // Selector or text to detect errors
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['web_form_target_id', 'sequence_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_form_pages');
    }
};
