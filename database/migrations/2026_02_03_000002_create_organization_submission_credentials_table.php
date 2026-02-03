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
        Schema::create('organization_submission_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            
            // Type of credential: 'ftp' for FTP submission, 'web' for web portal submission
            $table->enum('credential_type', ['ftp', 'web'])->default('ftp');
            
            // For web credentials, link to specific web form target (nullable for FTP)
            $table->foreignId('web_form_target_id')->nullable()->constrained()->onDelete('cascade');
            
            // Encrypted credentials JSON
            // For FTP: {trader_id, username, password, email}
            // For Web: {username, password, username_field, password_field, submit_selector}
            $table->text('credentials')->nullable();
            
            // Trader ID for CAPS FTP (6-digit identifier assigned by customs)
            $table->string('trader_id', 10)->nullable();
            
            // Display name for easy identification
            $table->string('display_name')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Unique constraint: one credential per org/country/type/target combination
            $table->unique(
                ['organization_id', 'country_id', 'credential_type', 'web_form_target_id'],
                'org_submission_creds_unique'
            );
            
            // Indexes for quick lookups (shortened names for MySQL compatibility)
            $table->index(['organization_id', 'credential_type'], 'org_cred_org_type_idx');
            $table->index(['country_id', 'credential_type'], 'org_cred_country_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_submission_credentials');
    }
};
