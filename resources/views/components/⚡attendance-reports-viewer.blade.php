<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Livewire\Component;

/** Spec C3: "Attendance summary reporting" — cross-employee, date-range. */
new class extends Component
{
    public string $startDate = '';

    public string $endDate = '';

    public ?int $employeeId = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function with(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $records = AttendanceRecord::whereBetween('punch_in_at_utc', [$start, $end])
            ->when($this->employeeId, fn ($q) => $q->where('employee_id', $this->employeeId))
            ->with('employee')
            ->get();

        $rows = $records->groupBy(fn ($r) => $r->employee->fullName())
            ->map(fn ($group, $label) => [
                'label' => $label,
                'punches' => $group->count(),
                'hours' => $group->sum(fn ($r) => $r->durationHours() ?? 0),
            ])->sortByDesc('hours')->values();

        return [
            'rows' => $rows,
            'employees' => Employee::orderBy('last_name')->get(),
            'totalHours' => $records->sum(fn ($r) => $r->durationHours() ?? 0),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">From</label>
                <input type="date" wire:model.live="startDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">To</label>
                <input type="date" wire:model.live="endDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                <select wire:model.live="employeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All employees</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                </select>
            </div>
            <div class="ml-auto text-sm text-text-muted">Total: <span class="font-mono font-semibold text-text">{{ number_format($totalHours, 2) }}h</span></div>
        </div>

        <div class="divide-y divide-border">
            @foreach($rows as $row)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <span class="text-text">{{ $row['label'] }}</span>
                    <span class="text-text-muted">{{ $row['punches'] }} {{ Str::plural('punch', $row['punches']) }}</span>
                    <span class="font-mono font-semibold text-text">{{ number_format($row['hours'], 2) }}h</span>
                </div>
            @endforeach
            @if($rows->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No attendance records in this range.</div>
            @endif
        </div>
    </section>
</div>
