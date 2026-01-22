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
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->foreignId('country_id')->after('id')->constrained()->onDelete('cascade');
            $table->string('hs_code_version', 10)->default('2022')->after('code');
            $table->index(['country_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->dropIndex(['country_id', 'code']);
            $table->dropForeign(['country_id']);
            $table->dropColumn(['country_id', 'hs_code_version']);
        });
    }
};
