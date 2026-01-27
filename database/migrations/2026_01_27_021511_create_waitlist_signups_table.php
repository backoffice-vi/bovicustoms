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
        Schema::create('waitlist_signups', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->json('interested_features')->nullable(); // ["bulk_processing", "more_countries", "api_access"]
            $table->string('source')->default('landing_page'); // Track where they signed up from
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('notified')->default(false); // Track if we've sent them updates
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_signups');
    }
};
