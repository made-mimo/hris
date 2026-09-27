<?php

use App\Models\Customer;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:150', 'unique:customers,name']]);
        Customer::create(['name' => $this->name]);
        $this->reset('name');
        session()->flash('status', 'Customer added.');
    }

    public function toggleActive(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $customer->update(['is_active' => ! $customer->is_active]);
    }

    public function delete(int $id): void
    {
        $customer = Customer::withCount('projects')->findOrFail($id);

        if ($customer->projects_count > 0) {
            session()->flash('error', "Can't delete \"{$customer->name}\" — {$customer->projects_count} project(s) still reference it.");

            return;
        }

        $customer->delete();
        session()->flash('status', 'Customer deleted.');
    }

    public function with(): array
    {
        return ['customers' => Customer::withCount('projects')->orderBy('name')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-danger/10 px-3.5 py-2.5 text-xs font-semibold text-danger">{{ session('error') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="create" class="mb-4 flex items-end gap-3">
            <div class="flex-1">
                <label class="mb-1.5 block text-xs font-semibold text-text">New customer</label>
                <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($customers as $c)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="font-medium text-text">{{ $c->name }}</span>
                        @if(! $c->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                        <span class="text-xs text-text-faint">{{ $c->projects_count }} project{{ $c->projects_count === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="toggleActive({{ $c->id }})" class="text-xs font-semibold text-primary">{{ $c->is_active ? 'Deactivate' : 'Activate' }}</button>
                        <button wire:click="delete({{ $c->id }})" wire:confirm="Delete this customer?" class="text-xs font-semibold text-danger">Delete</button>
                    </div>
                </div>
            @endforeach
            @if($customers->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No customers yet.</div>
            @endif
        </div>
    </section>
</div>
