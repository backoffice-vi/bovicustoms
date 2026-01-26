<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Schedule 1 - Goods Not Permitted to be Warehoused on Importation
     */
    public function up(): void
    {
        Schema::create('warehousing_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('legal_reference')->nullable();
            $table->json('exceptions')->nullable(); // e.g., "other than in tins packed in cases"
            $table->json('detection_keywords')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('country_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehousing_restrictions');
    }
};
