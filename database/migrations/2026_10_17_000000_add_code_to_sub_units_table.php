<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin backlog item 5d — a short department code (exactly 3 uppercase
 * letters, e.g. "SMA" for Sales & Marketing) for use in reports or wherever
 * a short form is needed. Nullable: existing departments predate this field
 * and aren't retroactively required to have one, but it's enforced on new
 * entries at the application layer (⚡sub-units-manager.blade.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_units', function (Blueprint $table) {
            $table->string('code', 3)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('sub_units', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
