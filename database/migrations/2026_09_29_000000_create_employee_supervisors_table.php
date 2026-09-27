<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec B2: "an employee may have multiple supervisors and multiple
 * subordinates simultaneously, each relationship additionally tagged with a
 * reporting method (direct vs. dotted-line/matrix) — a genuine many-to-many
 * typed graph, not a single manager field." `employees.supervisor_id`
 * (single self-FK, pre-dating this migration) stays in place as a synced
 * "primary direct supervisor" column — every workflow/approval-routing
 * consumer built in Phase 0/Section 8 (WorkflowEngine's supervisor actor
 * tag, the leave-request auto-routing, PermissionService's
 * self_subordinates scope) already depends on a single supervisor per
 * employee for exactly one purpose (who approves this person's requests),
 * and that stays correct and unchanged; this table is the real graph
 * everything else (org chart, multi-supervisor UI) reads from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('reporting_method', ['direct', 'dotted_line'])->default('direct');
            $table->timestamps();

            $table->unique(['employee_id', 'supervisor_id']);
        });

        $now = now();
        $rows = DB::table('employees')->whereNotNull('supervisor_id')->get(['id', 'supervisor_id']);
        foreach ($rows as $row) {
            DB::table('employee_supervisors')->insert([
                'employee_id' => $row->id,
                'supervisor_id' => $row->supervisor_id,
                'reporting_method' => 'direct',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_supervisors');
    }
};
