<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B2: "Organization-wide toggle to show/hide optional (non-required) profile fields." Implemented at tab granularity (hides the less-essential profile tabs when off), not per individual field — see PLAN.md for why. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('show_optional_profile_fields')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('show_optional_profile_fields');
        });
    }
};
