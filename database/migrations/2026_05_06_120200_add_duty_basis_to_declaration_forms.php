<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture which CUD basis ('fob' or 'cif') was used when the duty was last
 * computed for this declaration. Lets the T12 generator and the broker UI
 * read the basis without re-resolving the policy, and gives us an audit
 * trail when a policy window flips mid-shipment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->string('duty_basis', 8)->nullable()->after('total_duty');
        });
    }

    public function down(): void
    {
        Schema::table('declaration_forms', function (Blueprint $table) {
            $table->dropColumn('duty_basis');
        });
    }
};
