<?php

use App\Models\Employee;
use App\Models\PerformanceTrackerEntry;
use App\Services\PermissionService;
use Livewire\Component;

/** Spec D2: "an ongoing achievement log per employee, separate from formal reviews." */
new class extends Component
{
    public ?int $employeeId = null;

    public string $entryDate = '';

    public string $sentiment = 'positive';

    public string $description = '';

    public function mount(): void
    {
        $this->entryDate = now()->toDateString();
    }

    public function addEntry(): void
    {
        $data = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'entryDate' => ['required', 'date'],
            'sentiment' => ['required', 'in:positive,negative'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        PerformanceTrackerEntry::create([
            'employee_id' => $data['employeeId'],
            'entry_date' => $data['entryDate'],
            'sentiment' => $data['sentiment'],
            'description' => $data['description'],
            'created_by' => auth()->id(),
        ]);

        $this->reset('description');
        session()->flash('status', 'Entry logged.');
    }

    public function with(PermissionService $permissions): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'performance');

        $entries = PerformanceTrackerEntry::with(['employee', 'createdBy'])
            ->when($scope === 'self', fn ($q) => $q->where('employee_id', $me?->id))
            ->when($scope === 'self_subordinates', fn ($q) => $q->where(fn ($q2) => $q2->where('employee_id', $me?->id)->orWhereHas('employee', fn ($e) => $e->where('supervisor_id', $me?->id))))
            ->latest('entry_date')
            ->get();

        $employees = $scope === 'all'
            ? Employee::orderBy('last_name')->get()
            : Employee::where('id', $me?->id)->orWhere('supervisor_id', $me?->id)->orderBy('last_name')->get();

        return ['entries' => $entries, 'employees' => $employees];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Log entry</h2>
        <form wire:submit="addEntry" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                    <select wire:model="employeeId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                    </select>
                    @error('employeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Date</label>
                    <input type="date" wire:model="entryDate" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Sentiment</label>
                    <select wire:model="sentiment" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="positive">Positive</option>
                        <option value="negative">Negative</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Description</label>
                <textarea wire:model="description" rows="2" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                @error('description') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Add entry</button>
        </form>
    </section>

    <div class="flex flex-col gap-2">
        @foreach($entries as $entry)
            <div class="flex items-start gap-3 rounded-md border border-border bg-surface p-4 shadow-sm">
                <span class="mt-0.5 h-2 w-2 flex-shrink-0 rounded-full {{ $entry->sentiment === 'positive' ? 'bg-accent' : 'bg-danger' }}"></span>
                <div class="flex-1">
                    <div class="text-sm text-text">{{ $entry->description }}</div>
                    <div class="mt-1 text-xs text-text-muted">{{ $entry->employee->fullName() }} · {{ $entry->entry_date->format('j M Y') }} · logged by {{ $entry->createdBy->name }}</div>
                </div>
            </div>
        @endforeach
        @if($entries->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No tracker entries yet.</div>
        @endif
    </div>
</div>
