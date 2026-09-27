<?php

use App\Models\JobTitle;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public ?int $editingId = null;

    public string $editName = '';

    public bool $editActive = true;

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:150', 'unique:job_titles,name']]);

        JobTitle::create(['name' => $this->name]);
        $this->reset('name');
        session()->flash('status', 'Job title added.');
    }

    public function startEdit(int $id): void
    {
        $jobTitle = JobTitle::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $jobTitle->name;
        $this->editActive = $jobTitle->is_active;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:150', 'unique:job_titles,name,'.$this->editingId],
        ]);

        JobTitle::findOrFail($this->editingId)->update([
            'name' => $this->editName,
            'is_active' => $this->editActive,
        ]);
        $this->editingId = null;
        session()->flash('status', 'Job title updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        $jobTitle = JobTitle::withCount('employees')->findOrFail($id);

        if ($jobTitle->employees_count > 0) {
            session()->flash('error', "Can't delete \"{$jobTitle->name}\" — {$jobTitle->employees_count} employee(s) still use it.");

            return;
        }

        $jobTitle->delete();
        session()->flash('status', 'Job title deleted.');
    }

    public function with(): array
    {
        return ['jobTitles' => JobTitle::withCount('employees')->orderBy('name')->get()];
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
                <label for="name" class="mb-1.5 block text-xs font-semibold text-text">New job title</label>
                <input id="name" type="text" wire:model="name"
                    class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Add</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($jobTitles as $jt)
                <div class="flex items-center justify-between gap-3 py-2.5">
                    @if($editingId === $jt->id)
                        <div class="flex flex-1 items-center gap-3">
                            <input type="text" wire:model="editName" class="flex-1 rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            <label class="flex items-center gap-1.5 text-xs text-text">
                                <input type="checkbox" wire:model="editActive" class="h-4 w-4 accent-primary"> Active
                            </label>
                            <button wire:click="update" class="text-xs font-semibold text-primary">Save</button>
                            <button wire:click="cancelEdit" class="text-xs font-semibold text-text-muted">Cancel</button>
                        </div>
                        @error('editName') <div class="text-xs text-danger">{{ $message }}</div> @enderror
                    @else
                        <div class="flex items-center gap-2.5">
                            <span class="text-sm font-medium text-text">{{ $jt->name }}</span>
                            @if(! $jt->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                            <span class="text-xs text-text-faint">{{ $jt->employees_count }} employee{{ $jt->employees_count === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="startEdit({{ $jt->id }})" class="text-xs font-semibold text-primary">Edit</button>
                            <button wire:click="delete({{ $jt->id }})" wire:confirm="Delete this job title?" class="text-xs font-semibold text-danger">Delete</button>
                        </div>
                    @endif
                </div>
            @endforeach
            @if($jobTitles->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No job titles yet.</div>
            @endif
        </div>
    </section>
</div>
