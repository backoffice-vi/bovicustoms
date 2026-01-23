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
        // Change note_number from integer to string in tariff_chapter_notes
        Schema::table('tariff_chapter_notes', function (Blueprint $table) {
            $table->string('note_number', 20)->nullable()->change();
        });

        // Change note_number from integer to string in tariff_section_notes
        Schema::table('tariff_section_notes', function (Blueprint $table) {
            $table->string('note_number', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reversing this could cause data loss if there are non-numeric values
        Schema::table('tariff_chapter_notes', function (Blueprint $table) {
            $table->unsignedInteger('note_number')->nullable()->change();
        });

        Schema::table('tariff_section_notes', function (Blueprint $table) {
            $table->unsignedInteger('note_number')->nullable()->change();
        });
    }
};
