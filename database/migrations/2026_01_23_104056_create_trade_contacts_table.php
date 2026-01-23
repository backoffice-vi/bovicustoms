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
        Schema::create('trade_contacts', function (Blueprint $table) {
            $table->id();
            
            // Tenant scope
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Contact type
            $table->enum('contact_type', [
                'shipper',
                'consignee', 
                'broker',
                'notify_party',
                'bank',
                'other'
            ]);
            
            // Business information
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            
            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->onDelete('set null');
            
            // Communication
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            
            // Identifiers
            $table->string('tax_id')->nullable();
            $table->string('license_number')->nullable();
            
            // Banking information
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_routing')->nullable();
            
            // Additional
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['organization_id', 'contact_type']);
            $table->index(['user_id', 'contact_type']);
            $table->index('contact_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_contacts');
    }
};
