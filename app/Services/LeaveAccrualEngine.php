<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeavePeriod;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Spec C1's Accrual & Carry-Over Engine: "new recruits are not entitled to
 * leave until they have completed one year of service" (governed per-type
 * by `minimumTenureMonths`, not hard-coded to Annual Leave) and "unused
 * Annual Leave only carries over into Q1 of the following year, where it
 * expires if unused." Two independent jobs, both idempotent (safe to run
 * daily — each checks for a batch it would have already created before
 * creating another), matching spec's own note that "prorated_days...
 * rounded to the nearest configured rounding unit (default: nearest
 * half-day)."
 */
class LeaveAccrualEngine
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Spec: "First-year proration on reaching eligibility... the engine
     * grants a New-Hire Prorated entitlement batch... covering only the
     * remaining portion of that calendar year" — run daily so it fires the
     * moment (hire_date + minimumTenureMonths) is reached, whatever day of
     * the year that falls on.
     */
    public function grantNewHireProrations(): int
    {
        $granted = 0;

        foreach (LeaveType::where('minimum_tenure_months', '>', 0)->get() as $type) {
            $eligibleEmployees = Employee::whereRaw('DATE_ADD(hire_date, INTERVAL ? MONTH) <= ?', [$type->minimum_tenure_months, today()->toDateString()])
                ->whereDoesntHave('leaveEntitlements', fn ($q) => $q->where('leave_type_id', $type->id))
                ->get();

            foreach ($eligibleEmployees as $employee) {
                $eligibilityDate = $employee->hire_date->copy()->addMonths($type->minimum_tenure_months);
                $yearEnd = $eligibilityDate->copy()->endOfYear();

                // Full calendar months remaining AFTER the eligibility date's own
                // month, through December — spec's worked example: eligibility
                // 15-Mar means Apr–Dec = 9 months, not 10 (March itself doesn't
                // count, since the employee wasn't eligible for all of it).
                $monthsRemaining = max(0, 12 - $eligibilityDate->month);

                $proratedDays = round(((float) $type->standard_annual_days) * ($monthsRemaining / 12) * 2) / 2; // nearest half-day

                // Created even when 0 (eligibility landing in December) — this
                // row is also what marks the employee as "already processed"
                // for both this method's own idempotency and next year's
                // ordinary Standard Grant (see runYearEndCarryover()), so an
                // eligibility date late in the year still isn't a dead end.
                LeaveEntitlement::create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'year' => $eligibilityDate->year,
                    'entitled_days' => $proratedDays,
                    'batch_type' => LeaveEntitlement::BATCH_NEW_HIRE_PRORATED,
                    'effective_start_date' => $eligibilityDate->toDateString(),
                    'effective_end_date' => $yearEnd->toDateString(),
                ]);

                $granted++;
            }
        }

        return $granted;
    }

    /**
     * Spec's year-end carryover job — safe to run any day; only actually
     * acts once per employee/type/year (checked via whether a Carried Over
     * batch already exists for the new year) so a daily schedule can't
     * double-grant. Runs against "last year" relative to today, so it does
     * nothing until the calendar has actually turned over.
     */
    public function runYearEndCarryover(): int
    {
        $closingYear = today()->year - 1;
        $newYear = today()->year;

        if (today()->lt(Carbon::create($newYear, 1, 1))) {
            return 0;
        }

        $processed = 0;

        DB::transaction(function () use ($closingYear, $newYear, &$processed) {
            LeavePeriod::forYear($newYear);

            foreach (LeaveType::where('carries_over_at_year_end', true)->get() as $type) {
                foreach (Employee::whereHas('leaveEntitlements', fn ($q) => $q->where('leave_type_id', $type->id)->where('year', $closingYear))->get() as $employee) {
                    // Idempotency guard: skip if this employee/type/year has already been rolled over.
                    $alreadyRolled = LeaveEntitlement::where('employee_id', $employee->id)
                        ->where('leave_type_id', $type->id)
                        ->where('year', $newYear)
                        ->exists();

                    if ($alreadyRolled) {
                        continue;
                    }

                    $balance = app(LeaveBalanceService::class)->balance($employee, $type, $closingYear);
                    $unused = $balance['available'];
                    $carryover = $type->carryover_cap_days !== null ? min($unused, (float) $type->carryover_cap_days) : $unused;

                    if ($carryover > 0) {
                        LeaveEntitlement::create([
                            'employee_id' => $employee->id,
                            'leave_type_id' => $type->id,
                            'year' => $newYear,
                            'entitled_days' => $carryover,
                            'batch_type' => LeaveEntitlement::BATCH_CARRIED_OVER,
                            'effective_start_date' => "{$newYear}-01-01",
                            'effective_end_date' => "{$newYear}-03-31",
                            'expires_at' => "{$newYear}-03-31",
                        ]);

                        if ($employee->user) {
                            $this->notifications->notify(
                                $employee->user,
                                'leave.carryover',
                                'Carried-over leave expiring soon',
                                "You have {$carryover} day(s) of carried-over {$type->name} leave, expiring 31 March {$newYear}.",
                                '/leave/apply',
                                'Leave'
                            );
                        }
                    }

                    // Spec: "the new year's ordinary Standard Grant batch is created in the same job run."
                    LeaveEntitlement::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $type->id,
                        'year' => $newYear,
                        'entitled_days' => $type->standard_annual_days,
                        'batch_type' => LeaveEntitlement::BATCH_STANDARD,
                        'effective_start_date' => "{$newYear}-01-01",
                        'effective_end_date' => "{$newYear}-12-31",
                    ]);

                    $processed++;
                }
            }

            // Non-carrying types still need their new year's Standard Grant —
            // spec's job description is framed around carryover types, but an
            // employee shouldn't lose Sick/Compassionate/Study leave simply
            // because those types don't carry a balance forward.
            foreach (LeaveType::where('carries_over_at_year_end', false)->get() as $type) {
                foreach (Employee::whereHas('leaveEntitlements', fn ($q) => $q->where('leave_type_id', $type->id)->where('year', $closingYear))->get() as $employee) {
                    $alreadyGranted = LeaveEntitlement::where('employee_id', $employee->id)
                        ->where('leave_type_id', $type->id)
                        ->where('year', $newYear)
                        ->exists();

                    if ($alreadyGranted) {
                        continue;
                    }

                    LeaveEntitlement::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $type->id,
                        'year' => $newYear,
                        'entitled_days' => $type->standard_annual_days,
                        'batch_type' => LeaveEntitlement::BATCH_STANDARD,
                        'effective_start_date' => "{$newYear}-01-01",
                        'effective_end_date' => "{$newYear}-12-31",
                    ]);
                }
            }
        });

        return $processed;
    }
}
