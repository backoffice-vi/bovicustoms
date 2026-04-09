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
        Schema::table('caps_imports', function (Blueprint $table) {
            $table->json('ai_diagnosis')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('caps_imports', function (Blueprint $table) {
            $table->dropColumn('ai_diagnosis');
        });
    }
};
