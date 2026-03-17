<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove default from C104
        DB::table('country_reference_data')
            ->where('reference_type', 'cpc')
            ->where('code', 'C104')
            ->update(['is_default' => false]);

        // Set C400 (Release to Free Circulation) as default CPC
        DB::table('country_reference_data')
            ->where('reference_type', 'cpc')
            ->where('code', 'C400')
            ->update(['is_default' => true]);
    }

    public function down(): void
    {
        DB::table('country_reference_data')
            ->where('reference_type', 'cpc')
            ->where('code', 'C400')
            ->update(['is_default' => false]);

        DB::table('country_reference_data')
            ->where('reference_type', 'cpc')
            ->where('code', 'C104')
            ->update(['is_default' => true]);
    }
};
