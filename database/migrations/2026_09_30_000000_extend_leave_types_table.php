<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C1: "exclude from reports if unentitled" flag, and the year-end carryover job's Admin-configurable cap (default 10 days, confirmed by SI) — lives per-type since only types with carries_over_at_year_end=true ever use it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('exclude_from_reports_if_unentitled')->default(false);
            $table->decimal('carryover_cap_days', 5, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['exclude_from_reports_if_unentitled', 'carryover_cap_days']);
        });
    }
};
