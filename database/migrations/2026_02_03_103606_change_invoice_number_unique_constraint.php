<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Change invoice_number from globally unique to unique per organization.
     * This allows different organizations to have invoices with the same number.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop the existing unique constraint on invoice_number alone
            $table->dropUnique(['invoice_number']);
            
            // Add composite unique constraint: invoice_number must be unique within each organization
            $table->unique(['organization_id', 'invoice_number'], 'invoices_org_invoice_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('invoices_org_invoice_number_unique');
            
            // Restore the original unique constraint on invoice_number
            $table->unique(['invoice_number']);
        });
    }
};
