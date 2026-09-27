<?php

namespace App\Services;

use App\Models\LeaveEntitlement;
use App\Models\LeaveEntitlementConsumption;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;

/**
 * Spec C1: "an entitlement-consumption ledger recording exactly which
 * entitlement batch(es) paid for which leave day (supporting split
 * consumption across batches)" plus "FIFO consumption with expiry-aware
 * tie-break... the nearer-expiry batch is always drawn down first
 * regardless of creation order" — so a Q1-expiring Carried Over batch is
 * never left stranded behind the new year's full Standard Grant.
 */
class LeaveConsumptionService
{
    /** Called once a request reaches final approval — allocates every working day against the employee's open batches for that leave type, nearest-expiry first, splitting a day across batches if one runs out mid-day. */
    public function consume(LeaveRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $entitlements = LeaveEntitlement::where('employee_id', $request->employee_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->lockForUpdate()
                ->get()
                ->sort(fn (LeaveEntitlement $a, LeaveEntitlement $b) => $a->drawDownDeadline()->timestamp <=> $b->drawDownDeadline()->timestamp ?: $a->id <=> $b->id)
                ->values();

            $remaining = [];
            foreach ($entitlements as $e) {
                $remaining[$e->id] = $e->remainingDays();
            }

            foreach ($request->workingDays()->orderBy('date')->get() as $day) {
                $needed = (float) $day->day_value;

                foreach ($entitlements as $entitlement) {
                    if ($needed <= 0) {
                        break;
                    }
                    if (! $entitlement->isOpenOn($day->date) || $remaining[$entitlement->id] <= 0) {
                        continue;
                    }

                    $take = min($remaining[$entitlement->id], $needed);
                    LeaveEntitlementConsumption::create([
                        'leave_request_day_id' => $day->id,
                        'leave_entitlement_id' => $entitlement->id,
                        'days_consumed' => $take,
                    ]);
                    $remaining[$entitlement->id] -= $take;
                    $needed -= $take;
                }
            }
        });
    }

    /** Spec: "cancelling/rejecting leave reverses consumption and re-matches exactly as in the base model." */
    public function reverse(LeaveRequest $request): void
    {
        LeaveEntitlementConsumption::whereIn('leave_request_day_id', $request->requestDays()->pluck('id'))->delete();
    }
}
