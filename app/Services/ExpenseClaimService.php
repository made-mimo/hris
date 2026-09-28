<?php

namespace App\Services;

use App\Models\ClaimEvent;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimLine;
use App\Models\ExpenseType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TravelAdvance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec E1: "Self-service 'My Claims' and an HR/Admin 'assign claim to an
 * employee' path share the same underlying logic, differing only in whose
 * employee number is used" — this service is that shared logic, extracted
 * from the original self-service-only form so RecruitmentService-style
 * duplication doesn't creep in once the assign path exists.
 */
class ExpenseClaimService
{
    public function __construct(private WorkflowEngine $workflow, private NotificationService $notifications) {}

    /**
     * @param  array<int, array{type_id: int, date: string, note: ?string, amount: float}>  $lines
     */
    public function submit(Employee $employee, int $claimEventId, string $currency, array $lines): ExpenseClaim
    {
        return DB::transaction(function () use ($employee, $claimEventId, $currency, $lines) {
            $hasSupervisor = (bool) $employee->supervisor_id;

            $claim = ExpenseClaim::create([
                'reference' => 'CLM-'.now()->format('Ymd').'-'.str_pad((string) (ExpenseClaim::max('id') + 1), 3, '0', STR_PAD_LEFT),
                'employee_id' => $employee->id,
                'claim_event_id' => $claimEventId,
                'currency' => $currency,
                'status' => $hasSupervisor ? 'pending_manager' : 'pending_hr',
                'submitted_at' => now(),
                'manager_approved_at' => $hasSupervisor ? null : now(),
            ]);

            foreach ($lines as $line) {
                $type = ExpenseType::find($line['type_id']);
                $flagged = $type?->default_cap && $line['amount'] > $type->default_cap;

                ExpenseClaimLine::create([
                    'expense_claim_id' => $claim->id,
                    'expense_type_id' => $line['type_id'],
                    'date' => $line['date'],
                    'note' => $line['note'] ?: null,
                    'amount' => $line['amount'],
                    'flagged' => $flagged,
                ]);
            }

            $recipients = $hasSupervisor
                ? ($employee->supervisor?->user ? [$employee->supervisor->user] : [])
                : (Role::where('slug', 'hr_admin')->first()?->users ?? []);

            foreach ($recipients as $recipient) {
                $this->notifications->notify(
                    $recipient,
                    'expense_claim.submitted',
                    'New expense claim',
                    "{$employee->fullName()} submitted claim {$claim->reference} for {$claim->currency} ".number_format($claim->total(), 2).'.',
                    '/approvals',
                    'Expense Claim'
                );
            }

            return $claim;
        });
    }

    /** Spec E1: "marking a claim Paid requires a Payment Method and Payment Reference, recorded alongside the Paid Date." */
    public function markPaid(ExpenseClaim $claim, User $user, string $paymentMethod, string $paymentReference): void
    {
        $this->workflow->apply('expense_claim', $claim, $user, 'mark_paid');

        $claim->update([
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
        ]);

        if ($claim->employee->user) {
            $this->notifications->notify(
                $claim->employee->user,
                'expense_claim.paid',
                'Your expense claim has been paid',
                "Claim {$claim->reference} was paid via {$paymentMethod} (ref: {$paymentReference}).",
                '/claims/create',
                'Expense Claim'
            );
        }
    }

    public function requestTravelAdvance(Employee $employee, ClaimEvent $event, float $amount, string $currency): TravelAdvance
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The advance amount must be greater than zero.']);
        }

        $hasSupervisor = (bool) $employee->supervisor_id;

        return TravelAdvance::create([
            'employee_id' => $employee->id,
            'claim_event_id' => $event->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $hasSupervisor ? 'pending_manager' : 'pending_hr',
        ]);
    }

    public function markAdvancePaid(TravelAdvance $advance, User $user, string $paymentMethod, string $paymentReference): void
    {
        $this->workflow->apply('travel_advance', $advance, $user, 'mark_paid');

        $advance->update([
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
        ]);
    }

    /** Spec E1: "An advance with no reconciling claim submitted within a configurable window...surfaces on an 'Unreconciled Advances' report." */
    public function unreconciledAdvances(): Collection
    {
        $windowDays = Setting::current()->travel_advance_reconciliation_window_days;

        return TravelAdvance::where('status', 'paid')
            ->where('paid_at', '<=', now()->subDays($windowDays))
            ->with(['employee', 'claimEvent'])
            ->get()
            ->reject(fn (TravelAdvance $advance) => ExpenseClaim::where('employee_id', $advance->employee_id)
                ->where('claim_event_id', $advance->claim_event_id)
                ->exists()
            );
    }
}
