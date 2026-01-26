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
        Schema::create('submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_form_id')->constrained('declaration_forms')->onDelete('cascade');
            $table->foreignId('uploaded_by_user_id')->constrained('users')->onDelete('cascade');
            $table->string('file_name'); // Original filename
            $table->string('file_path'); // Storage path
            $table->string('file_type'); // MIME type
            $table->unsignedBigInteger('file_size'); // Size in bytes
            $table->enum('document_type', [
                'declaration_form',
                'commercial_invoice',
                'bill_of_lading',
                'air_waybill',
                'certificate_of_origin',
                'packing_list',
                'insurance_certificate',
                'import_permit',
                'other'
            ])->default('other');
            $table->string('description')->nullable();
            $table->timestamps();

            // Index for quick lookups
            $table->index('declaration_form_id');
            $table->index('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_attachments');
    }
};
