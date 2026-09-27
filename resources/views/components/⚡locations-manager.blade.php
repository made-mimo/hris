<?php

use App\Models\Location;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $address = '';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editAddress = '';

    public bool $editActive = true;

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150', 'unique:locations,name'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        Location::create(['name' => $this->name, 'address' => $this->address ?: null]);
        $this->reset('name', 'address');
        session()->flash('status', 'Location added.');
    }

    public function startEdit(int $id): void
    {
        $location = Location::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $location->name;
        $this->editAddress = $location->address ?? '';
        $this->editActive = $location->is_active;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:150', 'unique:locations,name,'.$this->editingId],
            'editAddress' => ['nullable', 'string', 'max:1000'],
        ]);

        Location::findOrFail($this->editingId)->update([
            'name' => $this->editName,
            'address' => $this->editAddress ?: null,
            'is_active' => $this->editActive,
        ]);
        $this->editingId = null;
        session()->flash('status', 'Location updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        $location = Location::withCount('employees')->findOrFail($id);

        if ($location->employees_count > 0) {
            session()->flash('error', "Can't delete \"{$location->name}\" — {$location->employees_count} employee(s) still use it.");

            return;
        }

        $location->delete();
        session()->flash('status', 'Location deleted.');
    }

    public function with(): array
    {
        return ['locations' => Location::withCount('employees')->orderBy('name')->get()];
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
        <form wire:submit="create" class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_2fr_auto] md:items-end">
            <div>
                <label for="name" class="mb-1.5 block text-xs font-semibold text-text">New location</label>
                <input id="name" type="text" wire:model="name"
                    class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="address" class="mb-1.5 block text-xs font-semibold text-text">Address <span class="text-text-muted">(optional)</span></label>
                <input id="address" type="text" wire:model="address"
                    class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Add</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($locations as $loc)
                <div class="flex items-center justify-between gap-3 py-2.5">
                    @if($editingId === $loc->id)
                        <div class="grid flex-1 grid-cols-1 gap-2 md:grid-cols-2">
                            <input type="text" wire:model="editName" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            <input type="text" wire:model="editAddress" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                        </div>
                        <label class="flex items-center gap-1.5 text-xs text-text">
                            <input type="checkbox" wire:model="editActive" class="h-4 w-4 accent-primary"> Active
                        </label>
                        <button wire:click="update" class="text-xs font-semibold text-primary">Save</button>
                        <button wire:click="cancelEdit" class="text-xs font-semibold text-text-muted">Cancel</button>
                    @else
                        <div class="flex items-center gap-2.5">
                            <span class="text-sm font-medium text-text">{{ $loc->name }}</span>
                            @if($loc->address) <span class="text-xs text-text-muted">{{ $loc->address }}</span> @endif
                            @if(! $loc->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                            <span class="text-xs text-text-faint">{{ $loc->employees_count }} employee{{ $loc->employees_count === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="startEdit({{ $loc->id }})" class="text-xs font-semibold text-primary">Edit</button>
                            <button wire:click="delete({{ $loc->id }})" wire:confirm="Delete this location?" class="text-xs font-semibold text-danger">Delete</button>
                        </div>
                    @endif
                </div>
            @endforeach
            @if($locations->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No locations yet.</div>
            @endif
        </div>
    </section>
</div>
