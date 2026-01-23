<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaration_form_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('declaration_form_id')->constrained('declaration_forms')->onDelete('cascade');

            // denormalized for speed + scoping
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('country_id')->constrained()->onDelete('restrict');

            $table->unsignedInteger('line_number')->nullable();
            $table->string('sku')->nullable();
            $table->string('item_number')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 3)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();

            // Approved categorization
            $table->string('hs_code')->nullable();
            $table->string('hs_description')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'country_id']);
            $table->index(['user_id', 'country_id']);
            $table->index('hs_code');
            $table->index('sku');
            $table->index('item_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaration_form_items');
    }
};

