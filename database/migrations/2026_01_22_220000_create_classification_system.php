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
        // Classification Exclusion Rules (parsed from chapter notes)
        Schema::create('classification_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->foreignId('source_chapter_id')->constrained('tariff_chapters')->onDelete('cascade');
            $table->string('exclusion_pattern'); // Pattern to match ("ceramic*", "glass*")
            $table->foreignId('target_chapter_id')->nullable()->constrained('tariff_chapters')->onDelete('set null');
            $table->string('target_heading')->nullable(); // Specific heading if known
            $table->text('rule_text'); // Human-readable explanation
            $table->string('source_note_reference')->nullable(); // "Chapter 84, Note 1(b)"
            $table->integer('priority')->default(0); // For overlapping rules
            $table->timestamps();

            $table->index(['source_chapter_id', 'exclusion_pattern'], 'excl_src_chapter_pattern_idx');
            $table->index('country_id');
        });

        // Exemption Categories (Schedule 5 items)
        Schema::create('exemption_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('legal_reference')->nullable(); // "Schedule 5, Para 19"
            $table->json('applies_to_patterns')->nullable(); // ["8471*", "8473*"] code patterns
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index('country_id');
            $table->index('is_active');
        });

        // Exemption Conditions (requirements for each exemption)
        Schema::create('exemption_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exemption_category_id')->constrained()->onDelete('cascade');
            $table->string('condition_type'); // time_limit, quantity, purpose, certificate, registration, notification
            $table->string('description');
            $table->text('requirement_text')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->index('exemption_category_id');
        });

        // Prohibited Goods (cannot import at all)
        Schema::create('prohibited_goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('legal_reference')->nullable();
            $table->json('detection_keywords')->nullable(); // For matching
            $table->timestamps();

            $table->index('country_id');
        });

        // Restricted Goods (require permit/conditions)
        Schema::create('restricted_goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('restriction_type')->nullable(); // permit, license, quota
            $table->string('permit_authority')->nullable(); // "Commissioner of Police"
            $table->text('requirements')->nullable();
            $table->json('detection_keywords')->nullable(); // For matching
            $table->timestamps();

            $table->index('country_id');
        });

        // Additional Levies (fuel levy, environmental charges, etc.)
        Schema::create('additional_levies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->foreignId('tariff_chapter_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('levy_name');
            $table->decimal('rate', 10, 4);
            $table->string('rate_type')->default('percentage'); // percentage, fixed_amount, per_unit
            $table->string('unit')->nullable(); // gallon, kg, etc.
            $table->string('legal_reference')->nullable();
            $table->json('exempt_organizations')->nullable(); // ["BVI Electricity Corp", "PWD"]
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('country_id');
            $table->index('tariff_chapter_id');
        });

        // Add classification aid fields to customs_codes
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->json('classification_keywords')->nullable(); // ["pump", "ceramic", "water"]
            $table->json('applicable_note_ids')->nullable(); // [12, 15, 23] quick note lookup
            $table->json('applicable_exemption_ids')->nullable(); // [1, 5] pre-computed exemptions
            $table->json('similar_code_ids')->nullable(); // [1234, 1235] alternatives
            $table->text('inclusion_hints')->nullable(); // "Includes electric and non-electric"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customs_codes', function (Blueprint $table) {
            $table->dropColumn([
                'classification_keywords',
                'applicable_note_ids',
                'applicable_exemption_ids',
                'similar_code_ids',
                'inclusion_hints'
            ]);
        });

        Schema::dropIfExists('additional_levies');
        Schema::dropIfExists('restricted_goods');
        Schema::dropIfExists('prohibited_goods');
        Schema::dropIfExists('exemption_conditions');
        Schema::dropIfExists('exemption_categories');
        Schema::dropIfExists('classification_exclusions');
    }
};
