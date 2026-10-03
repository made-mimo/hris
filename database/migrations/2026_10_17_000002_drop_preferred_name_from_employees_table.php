<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin backlog item 3 — preferred_name was a genuinely separate column
 * from first_name/last_name, but nothing in the app ever displayed it
 * (write-only since it was added). Removed at the user's request rather
 * than wiring it up to a use it didn't have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('preferred_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('preferred_name')->nullable()->after('last_name');
        });
    }
};
