<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table to change constraints
        if (DB::getDriverName() === 'sqlite') {
            // Disable foreign key checks
            DB::statement('PRAGMA foreign_keys = OFF');
            
            // Create new table with nullable tariff_section_id
            DB::statement('CREATE TABLE tariff_chapters_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tariff_section_id INTEGER NULL,
                chapter_number VARCHAR(10) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                country_id INTEGER NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (tariff_section_id) REFERENCES tariff_sections(id) ON DELETE SET NULL,
                FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
            )');
            
            // Copy data
            DB::statement('INSERT INTO tariff_chapters_new SELECT * FROM tariff_chapters');
            
            // Drop old table
            DB::statement('DROP TABLE tariff_chapters');
            
            // Rename new table
            DB::statement('ALTER TABLE tariff_chapters_new RENAME TO tariff_chapters');
            
            // Recreate unique index
            DB::statement('CREATE UNIQUE INDEX tariff_chapters_chapter_number_country_id_unique ON tariff_chapters (chapter_number, country_id)');
            
            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('tariff_chapters', function (Blueprint $table) {
                $table->foreignId('tariff_section_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We won't make it non-nullable again as that would cause data loss
    }
};
