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
        Schema::create('web_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_target_id')->constrained()->onDelete('cascade');
            $table->foreignId('declaration_form_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('set null');
            
            // Status tracking
            $table->enum('status', ['pending', 'in_progress', 'submitted', 'failed', 'confirmed', 'rejected'])->default('pending');
            
            // Data that was submitted
            $table->json('mapped_data')->nullable(); // Field values that were submitted
            $table->json('submission_log')->nullable(); // Step-by-step log with timestamps
            $table->json('ai_decisions')->nullable(); // AI decisions made during submission
            $table->json('screenshots')->nullable(); // Paths to screenshots taken
            
            // Result from external system
            $table->string('external_reference')->nullable(); // Reference number from external system
            $table->text('external_response')->nullable(); // Full response/message from external system
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->json('errors_encountered')->nullable(); // Errors that occurred and how they were handled
            $table->integer('retry_count')->default(0);
            
            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            $table->timestamps();
            
            $table->index(['declaration_form_id', 'status']);
            $table->index(['web_form_target_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index('external_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_form_submissions');
    }
};
