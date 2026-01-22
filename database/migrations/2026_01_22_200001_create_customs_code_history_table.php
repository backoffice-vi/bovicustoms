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
        Schema::create('customs_code_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customs_code_id')->constrained()->cascadeOnDelete();
            $table->string('field_changed');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('law_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('customs_code_id');
            $table->index('law_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customs_code_history');
    }
};
