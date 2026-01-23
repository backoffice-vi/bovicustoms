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
        Schema::table('law_documents', function (Blueprint $table) {
            // Store extracted text to avoid re-calling Unstructured API
            $table->longText('extracted_text')->nullable()->after('error_message');
            $table->timestamp('extracted_at')->nullable()->after('extracted_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('law_documents', function (Blueprint $table) {
            $table->dropColumn(['extracted_text', 'extracted_at']);
        });
    }
};
