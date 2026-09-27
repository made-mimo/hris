<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec C3 replaces Employee's plain clocked_in/clocked_in_at boolean+
 * timestamp (this prototype's very first iteration, predating Phase 2)
 * with real punch history. Any employee currently mid-punch gets an open
 * attendance_records row (no punch_out yet) so they aren't silently
 * clocked out by this migration — matches this project's "keep demo data"
 * rule throughout (Phase 1 Section 9, Phase 2 Section 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Employee::where('clocked_in', true)->whereNotNull('clocked_in_at')->get()->each(function (Employee $employee) {
            AttendanceRecord::create([
                'employee_id' => $employee->id,
                'punch_in_at_utc' => $employee->clocked_in_at,
                'punch_in_at_local' => $employee->clocked_in_at,
                'punch_in_timezone' => config('app.timezone'),
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['clocked_in', 'clocked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('clocked_in')->default(false);
            $table->timestamp('clocked_in_at')->nullable();
        });
    }
};
