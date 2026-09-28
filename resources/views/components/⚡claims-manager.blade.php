<?php

use App\Models\ClaimEvent;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Services\ExpenseClaimService;
use Livewire\Component;

/** Spec E1: "List filtering by reference number, status, event, employee, and submission-date range, including an option to include claims of terminated employees." */
new class extends Component
{
    public string $reference = '';

    public string $status = '';

    public ?int $claimEventId = null;

    public ?int $employeeId = null;

    public string $fromDate = '';

    public string $toDate = '';

    public bool $includeTerminated = false;

    public ?int $payingId = null;

    public string $paymentMethod = '';

    public string $paymentReference = '';

    public function markPaid(int $id, ExpenseClaimService $claims): void
    {
        $data = $this->validate([
            'paymentMethod' => ['required', 'string', 'max:100'],
            'paymentReference' => ['required', 'string', 'max:100'],
        ]);

        $claims->markPaid(ExpenseClaim::findOrFail($id), auth()->user(), $data['paymentMethod'], $data['paymentReference']);
        $this->reset('payingId', 'paymentMethod', 'paymentReference');
        session()->flash('status', 'Claim marked as paid.');
    }

    public function with(): array
    {
        $claims = ExpenseClaim::with(['employee', 'claimEvent', 'lines'])
            ->when($this->reference, fn ($q) => $q->where('reference', 'like', "%{$this->reference}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->claimEventId, fn ($q) => $q->where('claim_event_id', $this->claimEventId))
            ->when($this->employeeId, fn ($q) => $q->where('employee_id', $this->employeeId))
            ->when($this->fromDate, fn ($q) => $q->whereDate('submitted_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('submitted_at', '<=', $this->toDate))
            ->when(! $this->includeTerminated, fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereDoesntHave('terminations')))
            ->latest()
            ->get();

        return [
            'claims' => $claims,
            'employees' => Employee::orderBy('last_name')->get(),
            'claimEvents' => ClaimEvent::orderBy('name')->get(),
            'statuses' => ['draft', 'pending_manager', 'pending_hr', 'pending_second_approval', 'approved', 'rejected', 'paid'],
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Reference</label>
                <input type="text" wire:model.live="reference" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Status</label>
                <select wire:model.live="status" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All</option>
                    @foreach($statuses as $s)<option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Event</label>
                <select wire:model.live="claimEventId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All</option>
                    @foreach($claimEvents as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                <select wire:model.live="employeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">From</label>
                <input type="date" wire:model.live="fromDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">To</label>
                <input type="date" wire:model.live="toDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <label class="flex items-center gap-1.5 text-xs text-text">
                <input type="checkbox" wire:model.live="includeTerminated" class="h-4 w-4 accent-primary">
                Include terminated employees
            </label>
        </div>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Reference</th>
                    <th class="px-4 py-2.5">Employee</th>
                    <th class="px-4 py-2.5">Event</th>
                    <th class="px-4 py-2.5">Total</th>
                    <th class="px-4 py-2.5">Variance</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($claims as $claim)
                    <tr class="border-b border-border last:border-0 align-top">
                        <td class="px-4 py-2.5 font-mono text-text">{{ $claim->reference }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $claim->employee->fullName() }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $claim->claimEvent->name }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $claim->currency }} {{ number_format($claim->total(), 2) }}</td>
                        <td class="px-4 py-2.5 text-text">
                            @if($claim->reconciliationVariance() !== null)
                                <span class="{{ $claim->reconciliationVariance() > 0 ? 'text-danger' : 'text-accent' }}">{{ $claim->reconciliationVariance() > 0 ? 'Owed back' : 'Owed to employee' }} {{ number_format(abs($claim->reconciliationVariance()), 2) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2.5"><span class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text">{{ $claim->stageLabel() }}</span></td>
                        <td class="px-4 py-2.5">
                            @if($claim->status === 'approved')
                                @if($payingId === $claim->id)
                                    <div class="flex flex-col gap-1.5">
                                        <input type="text" wire:model="paymentMethod" placeholder="Payment method" class="rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                        <input type="text" wire:model="paymentReference" placeholder="Payment reference" class="rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                        <div class="flex gap-2">
                                            <button wire:click="markPaid({{ $claim->id }})" class="text-xs font-semibold text-accent">Confirm paid</button>
                                            <button wire:click="$set('payingId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                        </div>
                                    </div>
                                @else
                                    <button wire:click="$set('payingId', {{ $claim->id }})" class="text-xs font-semibold text-primary">Mark paid</button>
                                @endif
                            @endif
                            @foreach($claim->getMedia('receipts') as $media)
                                <a href="{{ $media->getUrl() }}" target="_blank" class="mt-1 block text-xs font-semibold text-primary">{{ $media->name }}</a>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                @if($claims->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="7">No matching claims.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
