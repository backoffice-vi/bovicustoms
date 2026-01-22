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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->string('role')->default('user')->after('organization_id');
            $table->boolean('is_individual')->default(false)->after('role');
            $table->foreignId('current_country_id')->nullable()->after('is_individual')->constrained('countries')->onDelete('set null');
            $table->boolean('onboarding_completed')->default(false)->after('current_country_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['current_country_id']);
            $table->dropColumn(['organization_id', 'role', 'is_individual', 'current_country_id', 'onboarding_completed']);
        });
    }
};
