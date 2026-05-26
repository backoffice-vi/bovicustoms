<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipping_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('shipping_documents', 'booking_number')) {
                $table->string('booking_number')->nullable()->after('manifest_number');
            }
            if (!Schema::hasColumn('shipping_documents', 'freight_grand_total')) {
                $table->decimal('freight_grand_total', 12, 2)->nullable()->after('other_charges');
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'booking_number')) {
                $table->string('booking_number')->nullable()->after('manifest_number');
            }
            if (!Schema::hasColumn('shipments', 'freight_base_amount')) {
                $table->decimal('freight_base_amount', 12, 2)->nullable()->after('freight_total');
            }
            if (!Schema::hasColumn('shipments', 'freight_other_charges')) {
                $table->decimal('freight_other_charges', 12, 2)->nullable()->after('freight_base_amount');
            }
            if (!Schema::hasColumn('shipments', 'freight_grand_total')) {
                $table->decimal('freight_grand_total', 12, 2)->nullable()->after('freight_other_charges');
            }
            if (!Schema::hasColumn('shipments', 'freight_total_source')) {
                $table->string('freight_total_source', 40)->default('document_freight_only')->after('freight_grand_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            foreach (['booking_number', 'freight_base_amount', 'freight_other_charges', 'freight_grand_total', 'freight_total_source'] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('shipping_documents', function (Blueprint $table) {
            foreach (['booking_number', 'freight_grand_total'] as $column) {
                if (Schema::hasColumn('shipping_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
