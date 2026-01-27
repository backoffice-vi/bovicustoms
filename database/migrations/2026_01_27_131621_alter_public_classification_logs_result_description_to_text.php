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
        Schema::table('public_classification_logs', function (Blueprint $table) {
            $table->text('result_description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_classification_logs', function (Blueprint $table) {
            $table->string('result_description', 500)->nullable()->change();
        });
    }
};
