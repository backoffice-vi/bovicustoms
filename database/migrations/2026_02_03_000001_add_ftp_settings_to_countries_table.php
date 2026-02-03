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
        Schema::table('countries', function (Blueprint $table) {
            // FTP submission settings
            $table->boolean('ftp_enabled')->default(false)->after('customs_form_template');
            $table->string('ftp_host')->nullable()->after('ftp_enabled');
            $table->integer('ftp_port')->default(21)->after('ftp_host');
            $table->boolean('ftp_passive_mode')->default(true)->after('ftp_port');
            $table->string('ftp_base_path')->nullable()->after('ftp_passive_mode');
            $table->string('ftp_file_format')->default('caps_t12')->after('ftp_base_path');
            
            // Submission methods available for this country
            $table->json('submission_methods')->nullable()->after('ftp_file_format');
            
            // FTP notification email (where CAPS sends confirmation emails)
            $table->string('ftp_notification_email')->nullable()->after('submission_methods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn([
                'ftp_enabled',
                'ftp_host',
                'ftp_port',
                'ftp_passive_mode',
                'ftp_base_path',
                'ftp_file_format',
                'submission_methods',
                'ftp_notification_email',
            ]);
        });
    }
};
