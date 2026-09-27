<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C2: "Timesheet line items (one row per project/activity, with a duration figure per day, recorded at a granularity supporting HH:MM entry)." One column per weekday (stored as decimal hours — HH:MM entry converts to/from this in the UI layer) rather than a further child table: a week always has exactly 7 days, so a fixed grid is the natural, not over-normalized, shape. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('project_activity_id')->constrained();
            $table->decimal('monday_hours', 4, 2)->default(0);
            $table->decimal('tuesday_hours', 4, 2)->default(0);
            $table->decimal('wednesday_hours', 4, 2)->default(0);
            $table->decimal('thursday_hours', 4, 2)->default(0);
            $table->decimal('friday_hours', 4, 2)->default(0);
            $table->decimal('saturday_hours', 4, 2)->default(0);
            $table->decimal('sunday_hours', 4, 2)->default(0);
            $table->timestamps();

            // Spec: "Duplicate-row prevention (same project+activity can't
            // appear twice on one timesheet — existing rows are updated in
            // place)."
            $table->unique(['timesheet_id', 'project_id', 'project_activity_id'], 'timesheet_lines_unique_combo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_lines');
    }
};
