<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetLine;
use Livewire\Component;

/** Spec C2: "Reporting: time-by-project/activity/employee reports." Only counts approved timesheets — a submitted-but-not-yet-approved timesheet's hours aren't final. */
new class extends Component
{
    public string $groupBy = 'project';

    public ?int $projectId = null;

    public function with(): array
    {
        $lines = TimesheetLine::whereHas('timesheet', fn ($q) => $q->where('status', 'approved'))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->with(['project', 'activity', 'timesheet.employee'])
            ->get();

        $grouped = match ($this->groupBy) {
            'activity' => $lines->groupBy(fn ($l) => $l->project->name.' · '.$l->activity->name),
            'employee' => $lines->groupBy(fn ($l) => $l->timesheet->employee->fullName()),
            default => $lines->groupBy(fn ($l) => $l->project->name),
        };

        $rows = $grouped->map(fn ($group, $label) => [
            'label' => $label,
            'hours' => $group->sum(fn ($l) => $l->totalHours()),
        ])->sortByDesc('hours')->values();

        return [
            'rows' => $rows,
            'projects' => Project::orderBy('name')->get(),
            'totalHours' => $lines->sum(fn ($l) => $l->totalHours()),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Group by</label>
                <select wire:model.live="groupBy" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="project">Project</option>
                    <option value="activity">Project + Activity</option>
                    <option value="employee">Employee</option>
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Project filter</label>
                <select wire:model.live="projectId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All projects</option>
                    @foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div class="ml-auto text-sm text-text-muted">Total: <span class="font-mono font-semibold text-text">{{ number_format($totalHours, 2) }}h</span></div>
        </div>

        @if($rows->isNotEmpty())
            <div class="mb-4">
                <x-chart-canvas
                    id="timesheet-hours"
                    type="bar"
                    :labels="$rows->pluck('label')->all()"
                    :datasets="[['label' => 'Hours', 'data' => $rows->pluck('hours')->all(), 'backgroundColor' => '#1E9E63']]"
                    :height="240"
                />
            </div>
        @endif

        <div class="divide-y divide-border">
            @foreach($rows as $row)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <span class="text-text">{{ $row['label'] }}</span>
                    <span class="font-mono font-semibold text-text">{{ number_format($row['hours'], 2) }}h</span>
                </div>
            @endforeach
            @if($rows->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No approved timesheet hours to report yet.</div>
            @endif
        </div>
    </section>
</div>
