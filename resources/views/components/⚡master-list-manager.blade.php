<?php

use App\Models\MasterListItem;
use Livewire\Component;

new class extends Component
{
    public string $type = MasterListItem::TYPE_JOB_CATEGORY;

    public string $name = '';

    public ?int $editingId = null;

    public string $editName = '';

    public bool $editActive = true;

    public function updatedType(): void
    {
        $this->editingId = null;
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150', 'unique:master_list_items,name,NULL,id,type,'.$this->type],
        ]);

        MasterListItem::create([
            'type' => $this->type,
            'name' => $this->name,
            'sort_order' => MasterListItem::ofType($this->type)->max('sort_order') + 1,
        ]);
        $this->reset('name');
        session()->flash('status', 'Item added.');
    }

    public function startEdit(int $id): void
    {
        $item = MasterListItem::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $item->name;
        $this->editActive = $item->is_active;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:150', 'unique:master_list_items,name,'.$this->editingId.',id,type,'.$this->type],
        ]);

        MasterListItem::findOrFail($this->editingId)->update([
            'name' => $this->editName,
            'is_active' => $this->editActive,
        ]);
        $this->editingId = null;
        session()->flash('status', 'Item updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        MasterListItem::findOrFail($id)->delete();
        session()->flash('status', 'Item deleted.');
    }

    public function moveUp(int $id): void
    {
        $this->swapOrder($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->swapOrder($id, 1);
    }

    protected function swapOrder(int $id, int $direction): void
    {
        $items = MasterListItem::ofType($this->type)->orderBy('sort_order')->orderBy('id')->get();
        $index = $items->search(fn ($i) => $i->id === $id);
        $swapWith = $items->get($index + $direction);

        if ($swapWith === null) {
            return;
        }

        $item = $items->get($index);
        [$a, $b] = [$item->sort_order, $swapWith->sort_order];
        $item->update(['sort_order' => $b]);
        $swapWith->update(['sort_order' => $a]);
    }

    public function with(): array
    {
        return [
            'items' => MasterListItem::ofType($this->type)->orderBy('sort_order')->orderBy('id')->get(),
            'types' => MasterListItem::TYPES,
        ];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-4">
            <label for="type" class="mb-1.5 block text-xs font-semibold text-text">List</label>
            <select id="type" wire:model.live="type" class="w-full max-w-xs rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary md:w-auto">
                @foreach($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <form wire:submit="create" class="mb-4 flex items-end gap-3">
            <div class="flex-1">
                <label for="name" class="mb-1.5 block text-xs font-semibold text-text">New item</label>
                <input id="name" type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Add</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($items as $item)
                <div class="flex items-center justify-between gap-3 py-2.5">
                    @if($editingId === $item->id)
                        <div class="flex flex-1 items-center gap-3">
                            <input type="text" wire:model="editName" class="flex-1 rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            <label class="flex items-center gap-1.5 text-xs text-text">
                                <input type="checkbox" wire:model="editActive" class="h-4 w-4 accent-primary"> Active
                            </label>
                            <button wire:click="update" class="text-xs font-semibold text-primary">Save</button>
                            <button wire:click="cancelEdit" class="text-xs font-semibold text-text-muted">Cancel</button>
                        </div>
                    @else
                        <div class="flex items-center gap-2.5">
                            <div class="flex flex-col">
                                <button wire:click="moveUp({{ $item->id }})" class="leading-none text-text-faint hover:text-text">▲</button>
                                <button wire:click="moveDown({{ $item->id }})" class="leading-none text-text-faint hover:text-text">▼</button>
                            </div>
                            <span class="text-sm font-medium text-text">{{ $item->name }}</span>
                            @if(! $item->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="startEdit({{ $item->id }})" class="text-xs font-semibold text-primary">Edit</button>
                            <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this item?" class="text-xs font-semibold text-danger">Delete</button>
                        </div>
                    @endif
                </div>
            @endforeach
            @if($items->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No items in this list yet.</div>
            @endif
        </div>
    </section>
</div>
