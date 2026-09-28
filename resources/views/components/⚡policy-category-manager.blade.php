<?php

use App\Models\PolicyCategory;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $description = '';

    public bool $isRestricted = false;

    public function create(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150', 'unique:policy_categories,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        PolicyCategory::create([
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'is_restricted' => $this->isRestricted,
            'sort_order' => PolicyCategory::max('sort_order') + 1,
        ]);

        $this->reset('name', 'description', 'isRestricted');
        session()->flash('status', 'Category added.');
    }

    public function toggleRestricted(int $id): void
    {
        $category = PolicyCategory::findOrFail($id);
        $category->update(['is_restricted' => ! $category->is_restricted]);
        session()->flash('status', 'Category updated.');
    }

    public function remove(int $id): void
    {
        $category = PolicyCategory::findOrFail($id);
        abort_if($category->documents()->exists(), 422, 'This category has documents filed under it and cannot be removed.');
        $category->delete();
        session()->flash('status', 'Category removed.');
    }

    public function with(): array
    {
        return ['categories' => PolicyCategory::withCount('documents')->orderBy('sort_order')->get()];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New category</h2>
        <form wire:submit="create" class="flex flex-wrap items-end gap-3">
            <div style="flex:1;min-width:200px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Name</label>
                <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div style="flex:2;min-width:240px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Description</label>
                <input type="text" wire:model="description" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <label class="flex items-center gap-1.5 pb-2.5 text-xs text-text">
                <input type="checkbox" wire:model="isRestricted" class="h-3.5 w-3.5 accent-primary">
                Restricted category
            </label>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Documents</th>
                    <th class="px-4 py-2.5">Restricted</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $category)
                    <tr class="border-b border-border last:border-0">
                        <td class="px-4 py-2.5 text-text">{{ $category->name }}<div class="text-xs text-text-muted">{{ $category->description }}</div></td>
                        <td class="px-4 py-2.5 text-text">{{ $category->documents_count }}</td>
                        <td class="px-4 py-2.5">
                            <button wire:click="toggleRestricted({{ $category->id }})" class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $category->is_restricted ? 'bg-warning-light text-warning' : 'bg-accent-light text-accent' }}">{{ $category->is_restricted ? 'Restricted' : 'Open' }}</button>
                        </td>
                        <td class="px-4 py-2.5">
                            <button wire:click="remove({{ $category->id }})" wire:confirm="Remove this category?" class="text-xs font-semibold text-danger">Remove</button>
                        </td>
                    </tr>
                @endforeach
                @if($categories->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="4">No categories yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
