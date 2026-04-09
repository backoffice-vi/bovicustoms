<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caps_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            $table->string('td_number')->index();
            $table->string('status')->default('pending')->index();

            $table->json('caps_data')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);

            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('declaration_form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('download_path')->nullable();

            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('invoices_processed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'td_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caps_imports');
    }
};
