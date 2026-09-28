<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec F2: "a daily HR-metrics snapshot...one row per calendar day, computed
 * nightly, giving genuine historical trend data that a live-query-only
 * design cannot provide." The recommended pattern the spec calls out for any
 * future "trend over time" reporting need — compute and store daily facts
 * rather than re-aggregating history live at every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_metrics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->unsignedInteger('active_headcount');
            $table->unsignedInteger('new_hires_trailing_30d');
            $table->unsignedInteger('terminations_trailing_30d');
            $table->decimal('turnover_rate_percent', 5, 2);
            $table->unsignedInteger('open_requisitions');
            $table->unsignedInteger('pending_leave_requests');
            $table->decimal('average_tenure_years', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_metrics_snapshots');
    }
};
