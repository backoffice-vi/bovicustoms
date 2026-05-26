<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            // IANA timezone identifier (e.g. America/Tortola, America/New_York).
            // Nullable; the Country model falls back to America/Tortola when null
            // because the platform was built BVI-first. Other deployments should
            // set this explicitly per country.
            $table->string('timezone', 64)->nullable()->after('flag_emoji');
        });

        // Backfill the BVI row so existing displays light up immediately.
        DB::table('countries')->where('code', 'VGB')->update([
            'timezone' => 'America/Tortola',
        ]);
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
