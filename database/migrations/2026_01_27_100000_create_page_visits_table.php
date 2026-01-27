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
        if (Schema::hasTable('page_visits')) {
            return; // Table already exists, skip creation
        }
        
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('visitor_id')->nullable()->index(); // For tracking returning visitors
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('url', 2048);
            $table->string('route_name')->nullable()->index();
            $table->string('method', 10)->default('GET');
            $table->string('referrer', 2048)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable(); // desktop, mobile, tablet
            $table->string('browser', 50)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->string('platform', 50)->nullable(); // OS
            $table->string('country', 100)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->integer('response_time_ms')->nullable(); // Response time in milliseconds
            $table->timestamps();

            // Indexes for common queries
            $table->index('created_at');
            $table->index(['created_at', 'is_bot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
