<?php

use App\Models\Employee;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public string $date = '';

    public string $reason = '';

    public string $note = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function record(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isHr(), 403);

        $this->validate([
            'date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->employee->terminations()->create([
            'date' => $this->date,
            'reason' => $this->reason,
            'note' => $this->note ?: null,
        ]);

        $this->reset('date', 'reason', 'note');
        session()->flash('status', 'Termination record added.');
    }

    public function with(): array
    {
        return [
            'terminations' => $this->employee->terminations()->orderByDesc('date')->get(),
            'canRecord' => auth()->user()->isAdmin() || auth()->user()->isHr(),
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
