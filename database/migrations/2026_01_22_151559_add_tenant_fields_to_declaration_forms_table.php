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
        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('country_id')->after('organization_id')->constrained()->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['country_id']);
            $table->dropColumn(['organization_id', 'country_id']);
        });
    }
};
