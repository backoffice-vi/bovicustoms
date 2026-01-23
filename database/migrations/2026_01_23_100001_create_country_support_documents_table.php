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
        Schema::create('country_support_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedBigInteger('file_size');
            $table->enum('document_type', [
                'tariff_schedule',
                'regulation',
                'guideline',
                'notice',
                'trade_agreement',
                'procedure_manual',
                'fee_schedule',
                'other'
            ])->default('other');
            $table->longText('extracted_text')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('country_id');
            $table->index('document_type');
            $table->index('status');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_support_documents');
    }
};
