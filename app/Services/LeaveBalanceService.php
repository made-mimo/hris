<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveEntitlementConsumption;
use App\Models\LeaveType;

/**
 * Spec C1: "Leave balance is always computed on demand as entitled minus
 * (scheduled + taken [+ pending, if configured to count against balance])
 * — never a cached running total, to avoid drift." `used` sums the
 * consumption ledger (only ever written at final approval — see
 * LeaveConsumptionService), which is exactly "scheduled + taken" together;
 * a still-pending request has no ledger rows yet, matching the base
 * policy of not counting pending against balance (the bracketed
 * "if configured" toggle isn't built — see PLAN.md).
 */
class LeaveBalanceService
{
    public function balance(Employee $employee, LeaveType $type, ?int $year = null): array
    {
        $year ??= now()->year;

        $entitled = (float) $employee->leaveEntitlements()
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->sum('entitled_days');

        $used = (float) LeaveEntitlementConsumption::whereHas('entitlement', function ($q) use ($employee, $type, $year) {
            $q->where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('year', $year);
        })->sum('days_consumed');

        return [
            'entitled' => $entitled,
            'used' => $used,
            'available' => max(0, $entitled - $used),
        ];
    }
}
