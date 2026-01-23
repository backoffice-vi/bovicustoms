<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_declaration_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_item_id')->constrained('invoice_items')->onDelete('cascade');
            $table->foreignId('declaration_form_item_id')->constrained('declaration_form_items')->onDelete('cascade');

            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('country_id')->constrained()->onDelete('restrict');

            $table->unsignedTinyInteger('confidence')->default(0); // 0-100
            $table->string('match_method')->default('heuristic'); // heuristic|ai|mixed
            $table->text('match_reason')->nullable();

            $table->timestamps();

            $table->unique(['invoice_item_id', 'declaration_form_item_id'], 'inv_decl_unique_pair');
            $table->index(['organization_id', 'country_id']);
            $table->index(['user_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_declaration_matches');
    }
};

