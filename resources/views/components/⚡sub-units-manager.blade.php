<?php

use App\Models\SubUnit;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public ?int $parentId = null;

    public ?int $editingId = null;

    public string $editName = '';

    public ?int $editParentId = null;

    public bool $editActive = true;

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150', 'unique:sub_units,name'],
            'parentId' => ['nullable', 'exists:sub_units,id'],
        ]);

        SubUnit::create(['name' => $this->name, 'parent_id' => $this->parentId]);
        $this->reset('name', 'parentId');
        session()->flash('status', 'Sub-unit added.');
    }

    public function startEdit(int $id): void
    {
        $subUnit = SubUnit::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $subUnit->name;
        $this->editParentId = $subUnit->parent_id;
        $this->editActive = $subUnit->is_active;
    }

    /** Every sub-unit's own descendant ids — a node can't become its own parent's descendant (would create a cycle in the adjacency-list tree). */
    protected function descendantIds(int $id): array
    {
        $ids = [];
        $queue = [$id];
        while ($queue) {
            $current = array_shift($queue);
            $children = SubUnit::where('parent_id', $current)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $queue = array_merge($queue, $children);
        }

        return $ids;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:150', 'unique:sub_units,name,'.$this->editingId],
            'editParentId' => ['nullable', 'exists:sub_units,id'],
        ]);

        if ($this->editParentId === $this->editingId || in_array($this->editParentId, $this->descendantIds($this->editingId), true)) {
            $this->addError('editParentId', 'A sub-unit cannot be moved under itself or one of its own children.');

            return;
        }

        SubUnit::findOrFail($this->editingId)->update([
            'name' => $this->editName,
            'parent_id' => $this->editParentId,
            'is_active' => $this->editActive,
        ]);
        $this->editingId = null;
        session()->flash('status', 'Sub-unit updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        $subUnit = SubUnit::withCount(['employees', 'children'])->findOrFail($id);

        if ($subUnit->children_count > 0) {
            session()->flash('error', "Can't delete \"{$subUnit->name}\" — it has {$subUnit->children_count} sub-unit(s) nested under it.");

            return;
        }

        if ($subUnit->employees_count > 0) {
            session()->flash('error', "Can't delete \"{$subUnit->name}\" — {$subUnit->employees_count} employee(s) still use it.");

            return;
        }

        $subUnit->delete();
        session()->flash('status', 'Sub-unit deleted.');
    }

    /** Flat list, depth-annotated by walking each row's parent chain — fine at this prototype's scale (spec B1 doesn't call for closure-table performance). */
    protected function flattenedTree(): array
    {
        $all = SubUnit::withCount('employees')->orderBy('name')->get();
        $byParent = $all->groupBy('parent_id');

        $rows = [];
        $walk = function ($parentId, $depth) use (&$walk, &$rows, $byParent) {
            foreach ($byParent->get($parentId, []) as $node) {
                $rows[] = ['node' => $node, 'depth' => $depth];
                $walk($node->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return $rows;
    }

    public function with(): array
    {
        return [
            'rows' => $this->flattenedTree(),
            'allSubUnits' => SubUnit::orderBy('name')->get(),
        ];
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
        <form wire:submit="create" class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
            <div>
                <label for="name" class="mb-1.5 block text-xs font-semibold text-text">New sub-unit</label>
                <input id="name" type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="parentId" class="mb-1.5 block text-xs font-semibold text-text">Parent <span class="text-text-muted">(optional)</span></label>
                <select id="parentId" wire:model="parentId" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                    <option value="">— top level —</option>
                    @foreach($allSubUnits as $su)
                        <option value="{{ $su->id }}">{{ $su->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Add</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($rows as $row)
                @php($su = $row['node'])
                <div class="flex items-center justify-between gap-3 py-2.5" style="padding-left: {{ $row['depth'] * 20 }}px;">
                    @if($editingId === $su->id)
                        <div class="grid flex-1 grid-cols-1 gap-2 md:grid-cols-2">
                            <input type="text" wire:model="editName" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            <select wire:model="editParentId" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                                <option value="">— top level —</option>
                                @foreach($allSubUnits as $opt)
                                    @if($opt->id !== $su->id)
                                        <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-1.5 text-xs text-text">
                            <input type="checkbox" wire:model="editActive" class="h-4 w-4 accent-primary"> Active
                        </label>
                        <button wire:click="update" class="text-xs font-semibold text-primary">Save</button>
                        <button wire:click="cancelEdit" class="text-xs font-semibold text-text-muted">Cancel</button>
                        @error('editParentId') <div class="text-xs text-danger">{{ $message }}</div> @enderror
                    @else
                        <div class="flex items-center gap-2.5">
                            @if($row['depth'] > 0) <span class="text-text-faint">└</span> @endif
                            <span class="text-sm font-medium text-text">{{ $su->name }}</span>
                            @if(! $su->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                            <span class="text-xs text-text-faint">{{ $su->employees_count }} employee{{ $su->employees_count === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="startEdit({{ $su->id }})" class="text-xs font-semibold text-primary">Edit</button>
                            <button wire:click="delete({{ $su->id }})" wire:confirm="Delete this sub-unit?" class="text-xs font-semibold text-danger">Delete</button>
                        </div>
                    @endif
                </div>
            @endforeach
            @if(empty($rows))
                <div class="py-6 text-center text-sm text-text-muted">No sub-units yet.</div>
            @endif
        </div>
    </section>
</div>
