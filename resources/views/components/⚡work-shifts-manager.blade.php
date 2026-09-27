<?php

use App\Models\WorkShift;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $hoursPerDay = '8.00';

    public string $startTime = '08:00';

    public string $endTime = '17:00';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editHoursPerDay = '';

    public string $editStartTime = '';

    public string $editEndTime = '';

    public bool $editActive = true;

    protected function rules(?int $ignoreId = null): array
    {
        return [
            ($ignoreId ? 'editName' : 'name') => ['required', 'string', 'max:150', 'unique:work_shifts,name'.($ignoreId ? ",{$ignoreId}" : '')],
            ($ignoreId ? 'editHoursPerDay' : 'hoursPerDay') => ['required', 'numeric', 'min:0.25', 'max:24'],
            ($ignoreId ? 'editStartTime' : 'startTime') => ['required'],
            ($ignoreId ? 'editEndTime' : 'endTime') => ['required'],
        ];
    }

    public function create(): void
    {
        $this->validate($this->rules());

        WorkShift::create([
            'name' => $this->name,
            'hours_per_day' => $this->hoursPerDay,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ]);
        $this->reset('name', 'hoursPerDay', 'startTime', 'endTime');
        $this->hoursPerDay = '8.00';
        $this->startTime = '08:00';
        $this->endTime = '17:00';
        session()->flash('status', 'Work shift added.');
    }

    public function startEdit(int $id): void
    {
        $shift = WorkShift::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $shift->name;
        $this->editHoursPerDay = (string) $shift->hours_per_day;
        $this->editStartTime = substr($shift->start_time, 0, 5);
        $this->editEndTime = substr($shift->end_time, 0, 5);
        $this->editActive = $shift->is_active;
    }

    public function update(): void
    {
        $this->validate($this->rules($this->editingId));

        WorkShift::findOrFail($this->editingId)->update([
            'name' => $this->editName,
            'hours_per_day' => $this->editHoursPerDay,
            'start_time' => $this->editStartTime,
            'end_time' => $this->editEndTime,
            'is_active' => $this->editActive,
        ]);
        $this->editingId = null;
        session()->flash('status', 'Work shift updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        WorkShift::findOrFail($id)->delete();
        session()->flash('status', 'Work shift deleted.');
    }

    public function with(): array
    {
        return ['shifts' => WorkShift::orderBy('start_time')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="create" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5 md:items-end">
            <div>
                <label for="name" class="mb-1.5 block text-xs font-semibold text-text">Name</label>
                <input id="name" type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="hoursPerDay" class="mb-1.5 block text-xs font-semibold text-text">Hours/day</label>
                <input id="hoursPerDay" type="number" step="0.25" wire:model="hoursPerDay" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('hoursPerDay') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="startTime" class="mb-1.5 block text-xs font-semibold text-text">Start</label>
                <input id="startTime" type="time" wire:model="startTime" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label for="endTime" class="mb-1.5 block text-xs font-semibold text-text">End</label>
                <input id="endTime" type="time" wire:model="endTime" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Add</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($shifts as $shift)
                <div class="flex items-center justify-between gap-3 py-2.5">
                    @if($editingId === $shift->id)
                        <div class="grid flex-1 grid-cols-2 gap-2 md:grid-cols-4">
                            <input type="text" wire:model="editName" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            <input type="number" step="0.25" wire:model="editHoursPerDay" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            <input type="time" wire:model="editStartTime" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            <input type="time" wire:model="editEndTime" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                        </div>
                        <label class="flex items-center gap-1.5 text-xs text-text">
                            <input type="checkbox" wire:model="editActive" class="h-4 w-4 accent-primary"> Active
                        </label>
                        <button wire:click="update" class="text-xs font-semibold text-primary">Save</button>
                        <button wire:click="cancelEdit" class="text-xs font-semibold text-text-muted">Cancel</button>
                    @else
                        <div class="flex items-center gap-2.5">
                            <span class="text-sm font-medium text-text">{{ $shift->name }}</span>
                            <span class="text-xs text-text-muted">{{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }} · {{ $shift->hours_per_day }}h/day</span>
                            @if(! $shift->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="startEdit({{ $shift->id }})" class="text-xs font-semibold text-primary">Edit</button>
                            <button wire:click="delete({{ $shift->id }})" wire:confirm="Delete this work shift?" class="text-xs font-semibold text-danger">Delete</button>
                        </div>
                    @endif
                </div>
            @endforeach
            @if($shifts->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No work shifts yet.</div>
            @endif
        </div>
    </section>
</div>
