<?php

use App\Models\NotificationSubscription;
use Livewire\Component;

/** Spec B1: "Email-notification subscription lists (which addresses receive which system notification categories)." Category is free text — see the migration's own comment for why. */
new class extends Component
{
    public string $category = '';

    public string $email = '';

    public function add(): void
    {
        $this->validate([
            'category' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:notification_subscriptions,email,NULL,id,category,'.$this->category],
        ]);

        NotificationSubscription::create(['category' => $this->category, 'email' => $this->email]);
        $this->reset('email');
        session()->flash('status', 'Subscription added.');
    }

    public function delete(int $id): void
    {
        NotificationSubscription::findOrFail($id)->delete();
        session()->flash('status', 'Subscription removed.');
    }

    public function with(): array
    {
        return ['subscriptions' => NotificationSubscription::orderBy('category')->orderBy('email')->get()->groupBy('category')];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3 text-xs text-text-muted">Category is free text (e.g. "leave_approved", "compliance_reminder") — one row per address per category.</div>
        <form wire:submit="add" class="mb-4 flex items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Category</label>
                <input type="text" wire:model="category" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div class="flex-1">
                <label class="mb-1.5 block text-xs font-semibold text-text">Email</label>
                <input type="email" wire:model="email" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('category') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('email') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        @forelse($subscriptions as $category => $rows)
            <div class="mb-3">
                <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-text-muted">{{ $category }}</div>
                <div class="divide-y divide-border">
                    @foreach($rows as $row)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-text">{{ $row->email }}</span>
                            <button wire:click="delete({{ $row->id }})" class="text-xs font-semibold text-danger">Remove</button>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="py-6 text-center text-sm text-text-muted">No subscriptions yet.</div>
        @endforelse
    </section>
</div>
