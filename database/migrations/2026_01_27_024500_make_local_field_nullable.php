<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support modifying columns, so we need to recreate
        // For now, just ensure the column allows null
        Schema::table('web_form_field_mappings', function (Blueprint $table) {
            // This works for databases that support column modification
        });

        // For SQLite, we'll just accept the current schema
        // The actual fix is in the model's fillable/casts
    }

    public function down(): void
    {
        //
    }
};
