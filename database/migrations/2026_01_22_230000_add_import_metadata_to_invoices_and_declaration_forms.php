<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('source_type')->default('generated')->after('status'); // generated|imported
            $table->string('source_file_path')->nullable()->after('source_type');
            $table->longText('extracted_text')->nullable()->after('source_file_path');
            $table->json('extraction_meta')->nullable()->after('extracted_text');
        });

        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->string('source_type')->default('generated')->after('invoice_id'); // generated|imported
            $table->string('source_file_path')->nullable()->after('source_type');
            $table->longText('extracted_text')->nullable()->after('source_file_path');
            $table->json('extraction_meta')->nullable()->after('extracted_text');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_file_path', 'extracted_text', 'extraction_meta']);
        });

        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_file_path', 'extracted_text', 'extraction_meta']);
        });
    }
};

