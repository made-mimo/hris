<?php

use App\Models\Asset;
use App\Models\Employee;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public string $date = '';

    public string $reason = '';

    public string $note = '';

    public bool $confirmingPurge = false;

    public string $purgeConfirmation = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    /** Spec E2 (retroactively wired now that Asset Management exists): "an employee's offboarding cannot be marked complete while they still have assets assigned to them — this is a hard block, not a soft reminder." */
    protected function assignedAssetNames(): array
    {
        return Asset::where('current_employee_id', $this->employee->id)->pluck('name')->all();
    }

    public function record(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isHr(), 403);

        $this->validate([
            'date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $assigned = $this->assignedAssetNames();
        if ($assigned) {
            $this->addError('date', 'This employee still has assets assigned to them ('.implode(', ', $assigned).') — return or reassign them before recording offboarding.');

            return;
        }

        $this->employee->terminations()->create([
            'date' => $this->date,
            'reason' => $this->reason,
            'note' => $this->note ?: null,
        ]);

        $this->reset('date', 'reason', 'note');
        session()->flash('status', 'Termination record added.');
    }

    /** Spec A6: GDPR purge — Admin-only, and gated behind typing the employee's exact Employee ID (a stronger confirmation than a plain confirm dialog, given this is irreversible). */
    public function purge(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($this->purgeConfirmation !== $this->employee->employee_id) {
            $this->addError('purgeConfirmation', 'Type the exact Employee ID to confirm.');

            return;
        }

        $this->employee->gdprPurge();
        $this->confirmingPurge = false;
        $this->reset('purgeConfirmation');
        session()->flash('status', 'Employee record purged — the Employee ID stays retired and will never be reissued.');
    }

    public function with(): array
    {
        return [
            'terminations' => $this->employee->terminations()->orderByDesc('date')->get(),
            'canRecord' => auth()->user()->isAdmin() || auth()->user()->isHr(),
            'canPurge' => auth()->user()->isAdmin(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3.5 flex items-center gap-3">
            <h2 class="font-display text-base font-bold text-text">Current status</h2>
            @if($employee->isTerminated())
                <span class="rounded-pill bg-danger/10 px-2.5 py-1 text-xs font-semibold text-danger">Terminated</span>
            @else
                <span class="rounded-pill bg-accent-light px-2.5 py-1 text-xs font-semibold text-accent">Active</span>
            @endif
        </div>
        <div class="text-xs text-text-muted">Derived from whether any termination record exists below (spec B2) — this prototype doesn't model a separate "rehire" event, so the most recent record's presence is the whole signal.</div>
    </section>

    @if($canRecord)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">Record termination</h2>
            <form wire:submit="record" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Date</label>
                    <input type="date" wire:model="date" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1.5 block text-xs font-semibold text-text">Reason</label>
                    <input type="text" wire:model="reason" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <button type="submit" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Record termination</button>
                <div class="w-full">
                    <label class="mb-1.5 block text-xs font-semibold text-text">Note <span class="text-text-muted">(optional)</span></label>
                    <textarea wire:model="note" rows="2" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                </div>
            </form>
            @error('date') <div class="mt-2 text-xs text-danger">{{ $message }}</div> @enderror
            @error('reason') <div class="mt-2 text-xs text-danger">{{ $message }}</div> @enderror
        </section>
    @endif

    @if($canPurge)
        <section class="rounded-md border border-danger/30 bg-danger/5 p-5 shadow-sm">
            <h2 class="mb-2 font-display text-base font-bold text-danger">GDPR purge</h2>
            @if($employee->is_gdpr_purged)
                <div class="text-sm text-text">This record was purged on {{ $employee->gdpr_purged_at->format('j M Y, g:ia') }}. The Employee ID stays retired and can never be reissued.</div>
            @else
                <div class="mb-3 text-xs text-text-muted">Spec Section A6 — irreversibly anonymizes this employee's personal data (name, contact details, government ID, immigration/compensation/qualification records) while permanently preserving the Employee ID as "used" so it's never reassigned. This cannot be undone.</div>
                @if(! $confirmingPurge)
                    <button wire:click="$set('confirmingPurge', true)" class="rounded-sm border border-danger px-4 py-2 text-sm font-semibold text-danger hover:bg-danger/10">Purge this employee's data</button>
                @else
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-text">Type "{{ $employee->employee_id }}" to confirm</label>
                            <input type="text" wire:model="purgeConfirmation" class="rounded-sm border border-danger bg-surface px-3 py-2 font-mono text-sm text-text outline-none">
                        </div>
                        <button wire:click="purge" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Confirm purge</button>
                        <button wire:click="$set('confirmingPurge', false)" class="rounded-sm border border-border px-4 py-2 text-sm font-semibold text-text-muted">Cancel</button>
                    </div>
                    @error('purgeConfirmation') <div class="mt-2 text-xs text-danger">{{ $message }}</div> @enderror
                @endif
            @endif
        </section>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">History</h2>
        <div class="divide-y divide-border">
            @foreach($terminations as $t)
                <div class="py-2.5 text-sm">
                    <div class="font-medium text-text">{{ $t->date->format('j M Y') }} — {{ $t->reason }}</div>
                    @if($t->note) <div class="mt-1 text-xs text-text-muted">{{ $t->note }}</div> @endif
                </div>
            @endforeach
            @if($terminations->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No termination records.</div>
            @endif
        </div>
    </section>
</div>
