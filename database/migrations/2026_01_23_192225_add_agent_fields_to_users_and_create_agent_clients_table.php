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
        // Add agent-specific fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('agent_license_number')->nullable()->after('role');
            $table->string('agent_company_name')->nullable()->after('agent_license_number');
            $table->text('agent_address')->nullable()->after('agent_company_name');
            $table->string('agent_phone')->nullable()->after('agent_address');
        });

        // Create agent_organization_clients table to link agents with their clients
        Schema::create('agent_organization_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->enum('status', ['pending', 'active', 'revoked'])->default('pending');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // An agent can only be linked to an organization once
            $table->unique(['agent_user_id', 'organization_id']);
            
            // Index for quick lookups
            $table->index('agent_user_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_organization_clients');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'agent_license_number',
                'agent_company_name',
                'agent_address',
                'agent_phone',
            ]);
        });
    }
};
