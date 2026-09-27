<?php

use App\Models\Holiday;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $date = '';

    public bool $isRecurringAnnual = true;

    public string $length = 'full';

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
        ]);

        Holiday::create([
            'name' => $this->name,
            'date' => $this->date,
            'is_recurring_annual' => $this->isRecurringAnnual,
            'length' => $this->length,
        ]);

        $this->reset('name', 'date');
        $this->isRecurringAnnual = true;
        $this->length = 'full';
        session()->flash('status', 'Holiday added.');
    }

    public function delete(int $id): void
    {
        Holiday::findOrFail($id)->delete();
        session()->flash('status', 'Holiday removed.');
    }

    public function with(): array
    {
        return ['holidays' => Holiday::orderBy('date')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="create" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5 md:items-end">
            <input type="text" wire:model="name" placeholder="Holiday name" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="date" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <select wire:model="length" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="full">Full day</option>
                <option value="half">Half day</option>
            </select>
            <label class="flex items-center gap-2 text-xs font-semibold text-text">
                <input type="checkbox" wire:model="isRecurringAnnual" class="h-4 w-4 accent-primary"> Recurs annually
            </label>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('name') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('date') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="divide-y divide-border">
            @foreach($holidays as $h)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <span>
                        <span class="font-medium text-text">{{ $h->name }}</span>
                        <span class="font-mono text-text-muted">{{ $h->date->format('j M') }}{{ $h->is_recurring_annual ? ' (yearly)' : ' ('.$h->date->year.' only)' }}</span>
                        @if($h->length === 'half') <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Half day</span> @endif
                    </span>
                    <button wire:click="delete({{ $h->id }})" wire:confirm="Remove this holiday?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($holidays->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No holidays configured yet.</div>
            @endif
        </div>
    </section>
</div>
