<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec C1's request lifecycle in one place: the two entry paths (self-
 * service Apply, always through approval; admin/supervisor Assign, direct
 * and optionally balance-check-bypassing), overlap prevention, and
 * double-checked balance enforcement (validated once in the UI via the
 * live balance figure, then again here under a row lock at save time, so
 * two concurrent requests can't both pass the first check and over-draw a
 * shared batch). WorkflowEngine stays domain-agnostic — this service's
 * `syncAfterTransition()` is the one place that reacts to a leave_request's
 * status actually changing (consume on approval, reverse on
 * reject/cancel), called by whichever screen just invoked
 * WorkflowEngine::apply() on a leave_request record.
 */
class LeaveRequestService
{
    public function __construct(
        private LeaveDayGeneratorService $dayGenerator,
        private LeaveConsumptionService $consumption,
    ) {}

    public function hasOverlap(Employee $employee, Carbon $start, Carbon $end, ?int $excludeRequestId = null): bool
    {
        return LeaveRequest::where('employee_id', $employee->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->when($excludeRequestId, fn ($q) => $q->where('id', '!=', $excludeRequestId))
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
    }

    /**
     * Self-service Apply — always lands in the approval workflow. Throws a
     * ValidationException (caught by the Livewire form the same way
     * $this->validate() failures are) rather than returning a bool, so the
     * caller doesn't need two different error-handling shapes for "bad
     * input" vs "business rule failed."
     */
    public function apply(Employee $employee, LeaveType $type, Carbon $start, Carbon $end, string $durationType, ?int $relieverId, ?string $reason): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $type, $start, $end, $durationType, $relieverId, $reason) {
            $generated = $this->generateAndValidate($employee, $type, $start, $end, $durationType, bypassBalance: false);

            $hasSupervisor = (bool) $employee->supervisor_id;

            $request = LeaveRequest::create([
                'reference' => $this->nextReference(),
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'reliever_employee_id' => $relieverId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'duration_type' => $durationType,
                'days' => $generated['totalDays'],
                'reason' => $reason,
                'is_assigned' => false,
                'status' => $hasSupervisor ? 'pending_manager' : 'pending_hr',
                'manager_approved_at' => $hasSupervisor ? null : now(),
            ]);

            foreach ($generated['rows'] as $row) {
                $request->requestDays()->create($row);
            }

            return $request;
        });
    }

    /** Admin/supervisor Assign — books directly as approved, bypassing the workflow entirely; $bypassBalance skips the entitlement check per spec's explicit "optionally bypassing the balance check." */
    public function assign(Employee $employee, LeaveType $type, Carbon $start, Carbon $end, string $durationType, ?string $reason, bool $bypassBalance): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $type, $start, $end, $durationType, $reason, $bypassBalance) {
            $generated = $this->generateAndValidate($employee, $type, $start, $end, $durationType, bypassBalance: $bypassBalance);

            $request = LeaveRequest::create([
                'reference' => $this->nextReference(),
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'duration_type' => $durationType,
                'days' => $generated['totalDays'],
                'reason' => $reason,
                'is_assigned' => true,
                'status' => 'approved',
                'manager_approved_at' => now(),
                'hr_approved_at' => now(),
            ]);

            foreach ($generated['rows'] as $row) {
                $request->requestDays()->create($row);
            }

            $this->finalizeApproval($request);

            return $request;
        });
    }

    /** @return array{rows: Collection, totalDays: float} */
    private function generateAndValidate(Employee $employee, LeaveType $type, Carbon $start, Carbon $end, string $durationType, bool $bypassBalance): array
    {
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['endDate' => 'End date must be on or after the start date.']);
        }

        if ($this->hasOverlap($employee, $start, $end)) {
            throw ValidationException::withMessages(['startDate' => 'This overlaps an existing leave request for this employee.']);
        }

        $generated = $this->dayGenerator->generate($start, $end, $durationType);

        if ($generated['totalDays'] <= 0) {
            throw ValidationException::withMessages(['endDate' => 'That range has no working days in it.']);
        }

        if (! $bypassBalance) {
            // Row-locks every open entitlement batch for this employee/type before
            // recomputing balance — the "double-checked... under a row lock at
            // save time" half of spec's enforcement (the UI's live figure is the
            // first check).
            LeaveEntitlement::where('employee_id', $employee->id)->where('leave_type_id', $type->id)->lockForUpdate()->get();

            $balance = app(LeaveBalanceService::class)->balance($employee, $type);
            if ($generated['totalDays'] > $balance['available']) {
                throw ValidationException::withMessages(['endDate' => "Only {$balance['available']} days available for {$type->name} leave."]);
            }
        }

        return $generated;
    }

    /** Call after WorkflowEngine::apply() (or any direct status write) changes a leave_request's status — reacts to whatever the new status now is. */
    public function syncAfterTransition(LeaveRequest $request): void
    {
        $request->refresh();

        match ($request->status) {
            'approved' => $this->finalizeApproval($request),
            'rejected', 'cancelled' => $this->finalizeTermination($request),
            default => null,
        };
    }

    /**
     * For a LeaveRequest header that already has its final `days` figure and
     * status decided some other way (this project's seeded demo data, or a
     * pre-existing row from before this per-day model existed) rather than
     * through apply()/assign() — generates day rows by evenly splitting the
     * header's own `days` across the range's weekend-only working dates
     * (deliberately not the full Holiday/Work-Week-aware calendar calc:
     * regenerating from scratch could produce a different total than the
     * header's already-decided, often hand-crafted figure, e.g. the demo
     * seeder's "12.5 of 20" narrative). Idempotent — a no-op if day rows
     * already exist.
     */
    public function materializeLegacyHeader(LeaveRequest $request): void
    {
        if ($request->requestDays()->exists()) {
            return;
        }

        $workingDates = [];
        for ($d = $request->start_date->copy(); $d->lte($request->end_date); $d->addDay()) {
            if (! $d->isWeekend()) {
                $workingDates[] = $d->copy();
            }
        }

        $perDayValue = count($workingDates) > 0 ? round((float) $request->days / count($workingDates), 2) : 0;

        for ($d = $request->start_date->copy(); $d->lte($request->end_date); $d->addDay()) {
            $isWorking = ! $d->isWeekend();

            $request->requestDays()->create([
                'date' => $d->copy(),
                'is_working_day' => $isWorking,
                'duration_type' => $request->duration_type,
                'day_value' => $isWorking ? $perDayValue : 0,
                'status' => ! $isWorking ? 'inert' : match ($request->status) {
                    'approved' => $d->lt(today()) ? 'taken' : 'scheduled',
                    'rejected' => 'rejected',
                    'cancelled', 'restricted' => 'cancelled',
                    default => 'pending',
                },
            ]);
        }

        if ($request->status === 'approved') {
            $this->consumption->consume($request);
        }
    }

    private function finalizeApproval(LeaveRequest $request): void
    {
        foreach ($request->workingDays as $day) {
            $day->update(['status' => $day->date->lt(today()) ? 'taken' : 'scheduled']);
        }

        $this->consumption->consume($request);
    }

    private function finalizeTermination(LeaveRequest $request): void
    {
        $this->consumption->reverse($request);
        $request->requestDays()->where('is_working_day', true)->update(['status' => $request->status]);
    }

    private function nextReference(): string
    {
        return 'LV-'.now()->year.'-'.str_pad((string) (LeaveRequest::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
