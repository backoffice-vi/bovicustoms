<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (!Schema::hasColumn('countries', 'ftp_response_path')) {
                $table->string('ftp_response_path')->nullable()->after('ftp_base_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (Schema::hasColumn('countries', 'ftp_response_path')) {
                $table->dropColumn('ftp_response_path');
            }
        });
    }
};
