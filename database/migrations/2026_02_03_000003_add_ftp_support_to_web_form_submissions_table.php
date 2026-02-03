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
        Schema::table('web_form_submissions', function (Blueprint $table) {
            // Add submission type to differentiate between web and FTP
            $table->string('submission_type')->default('web')->after('user_id');
            
            // Add request/response data columns for FTP
            $table->json('request_data')->nullable()->after('mapped_data');
            $table->json('response_data')->nullable()->after('request_data');
            
            // Add submitted_at timestamp
            $table->timestamp('submitted_at')->nullable()->after('started_at');
            
            // Add is_successful boolean for quick filtering
            $table->boolean('is_successful')->default(false)->after('status');
            
            // Make web_form_target_id nullable for FTP submissions
            $table->foreignId('web_form_target_id')->nullable()->change();
            
            // Add index for submission type
            $table->index('submission_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_form_submissions', function (Blueprint $table) {
            $table->dropIndex(['submission_type']);
            $table->dropColumn([
                'submission_type',
                'request_data',
                'response_data',
                'submitted_at',
                'is_successful',
            ]);
        });
    }
};
