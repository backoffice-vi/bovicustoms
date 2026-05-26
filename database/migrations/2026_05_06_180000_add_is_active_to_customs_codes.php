<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customs_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('customs_codes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('inclusion_hints');
                $table->index(['country_id', 'is_active']);
            }
            if (!Schema::hasColumn('customs_codes', 'source')) {
                $table->string('source', 100)->nullable()->after('inclusion_hints');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customs_codes', function (Blueprint $table) {
            if (Schema::hasColumn('customs_codes', 'is_active')) {
                $table->dropIndex(['country_id', 'is_active']);
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('customs_codes', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
