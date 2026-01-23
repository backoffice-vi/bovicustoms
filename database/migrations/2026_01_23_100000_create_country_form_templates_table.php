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
        Schema::create('country_form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedBigInteger('file_size');
            $table->enum('form_type', [
                'customs_declaration',
                'import_permit',
                'export_permit',
                'certificate_of_origin',
                'bill_of_lading',
                'commercial_invoice',
                'packing_list',
                'other'
            ])->default('other');
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('country_id');
            $table->index('form_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_form_templates');
    }
};
