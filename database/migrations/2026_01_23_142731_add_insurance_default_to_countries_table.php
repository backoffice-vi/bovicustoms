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
        Schema::table('countries', function (Blueprint $table) {
            $table->decimal('default_insurance_percentage', 5, 2)->nullable()->after('currency_code')
                ->comment('Default insurance percentage of FOB for new shipments');
            $table->string('default_insurance_method', 20)->default('percentage')->after('default_insurance_percentage')
                ->comment('Default insurance calculation method: manual, percentage, document');
        });

        // Set BVI default to 1%
        \DB::table('countries')
            ->where('code', 'VG')
            ->orWhere('name', 'like', '%British Virgin%')
            ->update([
                'default_insurance_percentage' => 1.00,
                'default_insurance_method' => 'percentage',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['default_insurance_percentage', 'default_insurance_method']);
        });
    }
};
