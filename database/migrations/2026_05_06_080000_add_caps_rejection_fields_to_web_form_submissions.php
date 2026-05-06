<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_form_submissions', function (Blueprint $table) {
            // CAPS-side response status, distinct from the FTP-layer `status`.
            // pending  = no CAPS response received yet (default after FTP upload)
            // accepted = CAPS accepted the declaration
            // rejected = CAPS rejected (Format Error Report / query report)
            // partial  = some records accepted, some rejected
            $table->string('caps_response_status', 20)
                ->default('pending')
                ->after('errors_encountered');

            $table->timestamp('caps_response_received_at')
                ->nullable()
                ->after('caps_response_status');

            // manual_upload = user uploaded the PDF query report
            // ftp_poll      = automated FTP poller picked up ETD..._REP.TXT (future)
            $table->string('caps_response_source', 20)
                ->nullable()
                ->after('caps_response_received_at');

            $table->string('caps_response_file_path', 500)
                ->nullable()
                ->after('caps_response_source');

            $table->json('caps_response_errors')
                ->nullable()
                ->after('caps_response_file_path');

            $table->unsignedBigInteger('parent_submission_id')
                ->nullable()
                ->after('caps_response_errors');

            $table->foreign('parent_submission_id')
                ->references('id')
                ->on('web_form_submissions')
                ->nullOnDelete();

            $table->index('caps_response_status');
            $table->index('parent_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('web_form_submissions', function (Blueprint $table) {
            $table->dropForeign(['parent_submission_id']);
            $table->dropIndex(['caps_response_status']);
            $table->dropIndex(['parent_submission_id']);
            $table->dropColumn([
                'caps_response_status',
                'caps_response_received_at',
                'caps_response_source',
                'caps_response_file_path',
                'caps_response_errors',
                'parent_submission_id',
            ]);
        });
    }
};
