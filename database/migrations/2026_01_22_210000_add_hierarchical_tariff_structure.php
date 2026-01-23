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
        // Tariff Sections (e.g., Section XVI - Machinery and mechanical appliances)
        Schema::create('tariff_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_number', 10); // I, II, XVI, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['section_number', 'country_id']);
        });

        // Section Notes (apply to all chapters in section)
        Schema::create('tariff_section_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_section_id')->constrained()->onDelete('cascade');
            $table->integer('note_number')->nullable();
            $table->string('note_type')->default('general'); // general, exclusion, definition, rule
            $table->text('note_text');
            $table->timestamps();
        });

        // Tariff Chapters (e.g., Chapter 84 - Nuclear reactors, boilers, machinery)
        Schema::create('tariff_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_section_id')->constrained()->onDelete('cascade');
            $table->string('chapter_number', 10); // 01, 84, 85, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['chapter_number', 'country_id']);
        });

        // Chapter Notes (apply to all headings in chapter)
        Schema::create('tariff_chapter_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_chapter_id')->constrained()->onDelete('cascade');
            $table->integer('note_number')->nullable();
            $table->string('note_type')->default('general'); // general, exclusion, definition, rule, subheading_note
            $table->text('note_text');
            $table->timestamps();
        });

        // Add hierarchical fields to customs_codes
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->foreignId('tariff_chapter_id')->nullable()->constrained()->onDelete('set null');
            $table->string('parent_code')->nullable(); // For hierarchy (8417.10 -> parent is 84.17)
            $table->string('code_level')->default('subheading'); // chapter, heading, subheading, item
            $table->string('unit_of_measurement')->nullable(); // kg, No, liters, etc.
            $table->string('unit_secondary')->nullable(); // For "kg and No" cases
            $table->decimal('special_rate', 8, 2)->nullable(); // Preferential/special rates
            $table->text('notes')->nullable(); // Item-specific notes
            
            $table->index('parent_code');
            $table->index('code_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->dropForeign(['tariff_chapter_id']);
            $table->dropColumn([
                'tariff_chapter_id',
                'parent_code',
                'code_level',
                'unit_of_measurement',
                'unit_secondary',
                'special_rate',
                'notes'
            ]);
        });

        Schema::dropIfExists('tariff_chapter_notes');
        Schema::dropIfExists('tariff_chapters');
        Schema::dropIfExists('tariff_section_notes');
        Schema::dropIfExists('tariff_sections');
    }
};
