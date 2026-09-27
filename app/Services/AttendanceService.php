<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec C3: punch in/out with row-locked race protection, overlap
 * prevention, and the three independently toggleable Admin-configurable
 * permissions (all off by default — only an Admin has edit/delete/proxy
 * rights until relaxed).
 */
class AttendanceService
{
    /** Spec: "An employee may only punch in when not already punched in... concurrent punch attempts are protected against race conditions with a row lock." */
    public function punchIn(Employee $employee, ?User $actor = null, ?Carbon $atTime = null): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $actor, $atTime) {
            $open = AttendanceRecord::where('employee_id', $employee->id)
                ->whereNull('punch_out_at_utc')
                ->lockForUpdate()
                ->first();

            if ($open) {
                throw ValidationException::withMessages(['punch' => 'Already punched in.']);
            }

            $actor ??= $employee->user;
            $timezone = $actor?->displayTimezone() ?? config('app.timezone');
            $utcTime = $atTime ?? now();

            $this->assertNoOverlap($employee, $utcTime, null);

            return AttendanceRecord::create([
                'employee_id' => $employee->id,
                'punch_in_at_utc' => $utcTime,
                'punch_in_at_local' => $utcTime->copy()->setTimezone($timezone),
                'punch_in_timezone' => $timezone,
                'is_proxy_punch' => $actor && $employee->user_id !== $actor->id,
                'recorded_by' => $actor?->id,
            ]);
        });
    }

    /** Spec: "...and only punch out when currently punched in." */
    public function punchOut(Employee $employee, ?User $actor = null, ?Carbon $atTime = null): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $actor, $atTime) {
            $open = AttendanceRecord::where('employee_id', $employee->id)
                ->whereNull('punch_out_at_utc')
                ->lockForUpdate()
                ->first();

            if (! $open) {
                throw ValidationException::withMessages(['punch' => 'Not currently punched in.']);
            }

            $actor ??= $employee->user;
            $timezone = $actor?->displayTimezone() ?? config('app.timezone');
            $utcTime = $atTime ?? now();

            $open->update([
                'punch_out_at_utc' => $utcTime,
                'punch_out_at_local' => $utcTime->copy()->setTimezone($timezone),
                'punch_out_timezone' => $timezone,
            ]);

            return $open;
        });
    }

    /** Spec: "No two attendance records for the same employee may have overlapping time ranges, validated on every create and edit." An open record (no punch-out yet) is treated as extending to "now" for overlap purposes. */
    public function assertNoOverlap(Employee $employee, Carbon $start, ?Carbon $end, ?int $excludeId = null): void
    {
        $effectiveEnd = $end ?? now();

        $overlaps = AttendanceRecord::where('employee_id', $employee->id)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('punch_in_at_utc', '<', $effectiveEnd)
            ->where(function ($q) use ($start) {
                $q->whereNull('punch_out_at_utc')->orWhere('punch_out_at_utc', '>', $start);
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['punch' => 'That overlaps an existing attendance record for this employee.']);
        }
    }

    public function canBackdateOwnPunch(): bool
    {
        return (bool) Setting::current()->attendance_allow_backdate;
    }

    public function canEditOwnRecords(User $user): bool
    {
        return $user->isAdmin() || (bool) Setting::current()->attendance_allow_self_edit;
    }

    public function canSupervisorProxyOrEdit(User $user): bool
    {
        return $user->isAdmin() || (bool) Setting::current()->attendance_allow_supervisor_proxy;
    }
}
