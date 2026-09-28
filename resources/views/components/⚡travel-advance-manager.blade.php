<?php

use App\Models\ClaimEvent;
use App\Models\Employee;
use App\Models\TravelAdvance;
use App\Services\ExpenseClaimService;
use App\Services\PermissionService;
use App\Services\WorkflowEngine;
use Livewire\Component;

/** Spec E1's Travel Advance & Reconciliation. */
new class extends Component
{
    public ?int $claimEventId = null;

    public string $amount = '';

    public string $currency = 'NGN';

    public ?int $payingId = null;

    public string $paymentMethod = '';

    public string $paymentReference = '';

    public function request(ExpenseClaimService $claims): void
    {
        $data = $this->validate([
            'claimEventId' => ['required', 'exists:claim_events,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $claims->requestTravelAdvance(auth()->user()->employee, ClaimEvent::findOrFail($data['claimEventId']), (float) $data['amount'], $this->currency);

        $this->reset('claimEventId', 'amount');
        session()->flash('status', 'Travel advance requested.');
    }

    public function act(int $id, string $action, WorkflowEngine $workflow): void
    {
        $workflow->apply('travel_advance', TravelAdvance::findOrFail($id), auth()->user(), $action);
        session()->flash('status', 'Advance updated.');
    }

    public function markPaid(int $id, ExpenseClaimService $claims): void
    {
        $data = $this->validate([
            'paymentMethod' => ['required', 'string', 'max:100'],
            'paymentReference' => ['required', 'string', 'max:100'],
        ]);

        $claims->markAdvancePaid(TravelAdvance::findOrFail($id), auth()->user(), $data['paymentMethod'], $data['paymentReference']);
        $this->reset('payingId', 'paymentMethod', 'paymentReference');
        session()->flash('status', 'Advance marked as paid.');
    }

    public function with(PermissionService $permissions, WorkflowEngine $workflow, ExpenseClaimService $claims): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'expense_claims');
        $isHr = $scope === 'all';

        $advances = TravelAdvance::with(['employee', 'claimEvent'])
            ->when(! $isHr, fn ($q) => $q->where('employee_id', $me?->id))
            ->latest()
            ->get();

        return [
            'advances' => $advances,
            'claimEvents' => ClaimEvent::where('active', true)->get(),
            'availableActions' => fn ($advance) => $workflow->availableTransitions('travel_advance', $advance->status, $user, $advance),
            'isHr' => $isHr,
            'unreconciled' => $isHr ? $claims->unreconciledAdvances() : collect(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Request an advance</h2>
        <form wire:submit="request" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Claim event</label>
                <select wire:model="claimEventId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">— select —</option>
                    @foreach($claimEvents as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                </select>
                @error('claimEventId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Amount</label>
                <input type="number" step="0.01" wire:model="amount" class="w-40 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('amount') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Request advance</button>
        </form>
    </section>

    @if($isHr && $unreconciled->isNotEmpty())
        <section class="rounded-md border border-warning/30 bg-warning-light p-5 shadow-sm">
            <h2 class="mb-2 font-display text-base font-bold text-text">Unreconciled Advances</h2>
            @foreach($unreconciled as $a)
                <div class="text-xs text-text">{{ $a->employee->fullName() }} — {{ $a->claimEvent->name }} — {{ $a->currency }} {{ number_format($a->amount, 2) }} paid {{ $a->paid_at->format('j M Y') }}, no reconciling claim submitted.</div>
            @endforeach
        </section>
    @endif

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Employee</th>
                    <th class="px-4 py-2.5">Event</th>
                    <th class="px-4 py-2.5">Amount</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($advances as $advance)
                    <tr class="border-b border-border last:border-0 align-top">
                        <td class="px-4 py-2.5 text-text">{{ $advance->employee->fullName() }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $advance->claimEvent->name }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $advance->currency }} {{ number_format($advance->amount, 2) }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text">{{ $advance->stageLabel() }}</span></td>
                        <td class="px-4 py-2.5">
                            @if($payingId === $advance->id)
                                <div class="flex flex-col gap-1.5">
                                    <input type="text" wire:model="paymentMethod" placeholder="Payment method" class="rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                    <input type="text" wire:model="paymentReference" placeholder="Payment reference" class="rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                    <div class="flex gap-2">
                                        <button wire:click="markPaid({{ $advance->id }})" class="text-xs font-semibold text-accent">Confirm paid</button>
                                        <button wire:click="$set('payingId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                    </div>
                                </div>
                            @else
                                <div class="flex gap-2">
                                    @foreach($availableActions($advance) as $transition)
                                        @if($transition->action === 'mark_paid')
                                            <button wire:click="$set('payingId', {{ $advance->id }})" class="text-xs font-semibold text-primary">{{ $transition->label }}</button>
                                        @else
                                            <button wire:click="act({{ $advance->id }}, '{{ $transition->action }}')" class="text-xs font-semibold text-primary">{{ $transition->label }}</button>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if($advances->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="5">No travel advances yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
