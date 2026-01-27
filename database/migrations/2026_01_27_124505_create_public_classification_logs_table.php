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
        Schema::create('public_classification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('search_term', 500);
            $table->string('result_code', 20)->nullable();
            $table->string('result_description', 500)->nullable();
            $table->decimal('duty_rate', 8, 2)->nullable();
            $table->integer('confidence')->nullable();
            $table->decimal('vector_score', 8, 4)->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_message', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('source', 50)->default('landing_page');
            $table->timestamps();
            
            // Indexes for analytics queries
            $table->index('search_term');
            $table->index('result_code');
            $table->index('created_at');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_classification_logs');
    }
};
