<?php

use App\Models\Employee;
use App\Models\Goal;
use App\Services\PermissionService;
use Livewire\Component;

new class extends Component
{
    public ?int $employeeId = null;

    public string $title = '';

    public string $description = '';

    public string $targetValue = '';

    public string $currentValue = '';

    public string $unit = '';

    public string $dueDate = '';

    public function create(): void
    {
        $data = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'targetValue' => ['nullable', 'numeric'],
            'currentValue' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:30'],
            'dueDate' => ['nullable', 'date'],
        ]);

        Goal::create([
            'employee_id' => $data['employeeId'],
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'target_value' => $data['targetValue'] ?: null,
            'current_value' => $data['currentValue'] ?: null,
            'unit' => $data['unit'] ?: null,
            'due_date' => $data['dueDate'] ?: null,
            'status' => 'not_started',
        ]);

        $this->reset('title', 'description', 'targetValue', 'currentValue', 'unit', 'dueDate');
        session()->flash('status', 'Goal added.');
    }

    public function updateStatus(int $id, string $status): void
    {
        Goal::findOrFail($id)->update(['status' => $status]);
    }

    public function with(PermissionService $permissions): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'performance');

        $goals = Goal::with('employee')
            ->when($scope === 'self', fn ($q) => $q->where('employee_id', $me?->id))
            ->when($scope === 'self_subordinates', fn ($q) => $q->where(fn ($q2) => $q2->where('employee_id', $me?->id)->orWhereHas('employee', fn ($e) => $e->where('supervisor_id', $me?->id))))
            ->latest()
            ->get();

        $employees = $scope === 'all'
            ? Employee::orderBy('last_name')->get()
            : Employee::where('id', $me?->id)->orWhere('supervisor_id', $me?->id)->orderBy('last_name')->get();

        return ['goals' => $goals, 'employees' => $employees];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New goal</h2>
        <form wire:submit="create" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                    <select wire:model="employeeId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                    </select>
                    @error('employeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Title</label>
                    <input type="text" wire:model="title" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('title') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Description</label>
                <textarea wire:model="description" rows="2" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
            </div>
            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Target</label>
                    <input type="number" step="0.01" wire:model="targetValue" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Current</label>
                    <input type="number" step="0.01" wire:model="currentValue" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Unit</label>
                    <input type="text" wire:model="unit" placeholder="e.g. %, units" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Due date</label>
                    <input type="date" wire:model="dueDate" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
            </div>
            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Add goal</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Employee</th>
                    <th class="px-4 py-2.5">Goal</th>
                    <th class="px-4 py-2.5">Progress</th>
                    <th class="px-4 py-2.5">Due</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($goals as $goal)
                    <tr class="border-b border-border last:border-0">
                        <td class="px-4 py-2.5 text-text">{{ $goal->employee->fullName() }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $goal->title }}</td>
                        <td class="px-4 py-2.5 text-text">
                            @if($goal->target_value)
                                {{ $goal->current_value ?? 0 }}/{{ $goal->target_value }} {{ $goal->unit }}
                                <span class="text-xs text-text-muted">({{ $goal->progressPercent() }}%)</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-text">{{ $goal->due_date?->format('j M Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <select wire:change="updateStatus({{ $goal->id }}, $event.target.value)" class="rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                @foreach(\App\Models\Goal::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($goal->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
                @if($goals->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="5">No goals yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
