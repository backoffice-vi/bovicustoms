<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE organization_submission_credentials MODIFY COLUMN credential_type ENUM('ftp', 'web', 'caps') DEFAULT 'ftp'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE organization_submission_credentials MODIFY COLUMN credential_type ENUM('ftp', 'web') DEFAULT 'ftp'");
    }
};
