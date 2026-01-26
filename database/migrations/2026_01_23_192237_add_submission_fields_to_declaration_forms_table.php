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
        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->enum('submission_status', ['draft', 'ready', 'submitted', 'accepted', 'rejected'])
                ->default('draft')
                ->after('source_file_path');
            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->after('submission_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by_user_id');
            $table->string('submission_reference')->nullable()->after('submitted_at');
            $table->text('submission_notes')->nullable()->after('submission_reference');
            $table->timestamp('submission_response_at')->nullable()->after('submission_notes');
            $table->text('submission_response')->nullable()->after('submission_response_at');

            // Index for filtering by submission status
            $table->index('submission_status');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->dropForeign(['submitted_by_user_id']);
            $table->dropIndex(['submission_status']);
            $table->dropIndex(['submitted_at']);
            $table->dropColumn([
                'submission_status',
                'submitted_by_user_id',
                'submitted_at',
                'submission_reference',
                'submission_notes',
                'submission_response_at',
                'submission_response',
            ]);
        });
    }
};
