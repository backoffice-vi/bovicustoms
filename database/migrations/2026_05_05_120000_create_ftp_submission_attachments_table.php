<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ftp_submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_submission_id')
                ->constrained('web_form_submissions')
                ->cascadeOnDelete();
            $table->char('letter', 1);
            $table->string('original_filename');
            $table->string('remote_filename');
            $table->string('local_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('source_type', 30)->nullable();
            $table->string('source_reference')->nullable();
            $table->enum('status', ['pending', 'uploaded', 'confirmed', 'rejected', 'failed'])
                ->default('pending');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->text('error_message')->nullable();
            $table->text('caps_response_text')->nullable();
            $table->timestamps();

            $table->unique(['web_form_submission_id', 'letter'], 'fsa_submission_letter_unique');
            $table->index('remote_filename', 'fsa_remote_filename_idx');
            $table->index('status', 'fsa_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ftp_submission_attachments');
    }
};
