<?php

use App\Models\ProjectActivity;
use App\Models\Timesheet;
use App\Models\TimesheetLine;
use App\Services\TimesheetService;
use App\Services\WorkflowEngine;
use Carbon\Carbon;
use Livewire\Component;

/** Spec C2: weekly timesheet entry against the employee's assigned projects. One row per project+activity, a column per weekday — "duration figure per day, recorded at a granularity supporting HH:MM entry" (stored as decimal hours; the input itself is a plain number field, step 0.25, which is the same HH:MM-equivalent granularity without a separate time-picker widget). */
new class extends Component
{
    public string $weekStart;

    public ?int $newProjectId = null;

    public ?int $newActivityId = null;

    public array $hours = [];

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    protected function employee()
    {
        return auth()->user()->employee;
    }

    protected function timesheet(): Timesheet
    {
        return app(TimesheetService::class)->findOrCreateForEmployee($this->employee(), Carbon::parse($this->weekStart));
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function addLine(): void
    {
        $this->validate([
            'newProjectId' => ['required', 'exists:projects,id'],
            'newActivityId' => ['required', 'exists:project_activities,id'],
        ]);

        app(TimesheetService::class)->upsertLine($this->timesheet(), $this->newProjectId, $this->newActivityId, []);
        $this->reset('newProjectId', 'newActivityId');
    }

    public function saveHours(): void
    {
        $timesheet = $this->timesheet();

        abort_unless(in_array($timesheet->status, ['not_submitted', 'rejected'], true), 403, 'Only a not-submitted or rejected timesheet can be edited.');

        foreach ($this->hours as $lineId => $days) {
            $line = TimesheetLine::where('timesheet_id', $timesheet->id)->find($lineId);

            if ($line) {
                $line->update(array_map(fn ($v) => (float) $v ?: 0, $days));
            }
        }

        session()->flash('status', 'Hours saved.');
    }

    public function deleteLine(int $lineId): void
    {
        TimesheetLine::where('timesheet_id', $this->timesheet()->id)->where('id', $lineId)->delete();
    }

    public function submit(WorkflowEngine $engine): void
    {
        $timesheet = $this->timesheet();
        $wasRejected = $timesheet->status === 'rejected';

        $engine->apply('timesheet', $timesheet, auth()->user(), $wasRejected ? 'resubmit' : 'submit');
        $timesheet->update(['submitted_at' => now()]);
        $timesheet->logAction(auth()->user(), $wasRejected ? 'resubmitted' : 'submitted');
        session()->flash('status', 'Timesheet submitted for approval.');
    }

    public function with(): array
    {
        $timesheet = $this->timesheet();
        $lines = $timesheet->lines()->with(['project', 'activity'])->get();

        foreach ($lines as $line) {
            if (! isset($this->hours[$line->id])) {
                foreach (Timesheet::DAY_COLUMNS as $col) {
                    $this->hours[$line->id][$col] = (string) $line->{$col};
                }
            }
        }

        $employee = $this->employee();
        $assignedProjectIds = $employee->projectAssignments()->pluck('project_id');

        return [
            'timesheet' => $timesheet,
            'lines' => $lines,
            'projects' => \App\Models\Project::whereIn('id', $assignedProjectIds)->where('is_active', true)->orderBy('name')->get(),
            'activities' => $this->newProjectId ? ProjectActivity::where('project_id', $this->newProjectId)->where('is_active', true)->orderBy('name')->get() : collect(),
            'canEdit' => in_array($timesheet->status, ['not_submitted', 'rejected'], true),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button wire:click="previousWeek" class="rounded-sm border border-border px-3 py-1.5 text-sm text-text hover:bg-bg">←</button>
                <span class="text-sm font-semibold text-text">{{ \Carbon\Carbon::parse($weekStart)->format('j M') }} – {{ \Carbon\Carbon::parse($weekStart)->addDays(6)->format('j M Y') }}</span>
                <button wire:click="nextWeek" class="rounded-sm border border-border px-3 py-1.5 text-sm text-text hover:bg-bg">→</button>
            </div>
            <span class="rounded-pill bg-text-faint/15 px-3 py-1 text-xs font-semibold text-text-muted">{{ ucfirst(str_replace('_', ' ', $timesheet->status)) }}</span>
        </div>

        @if($timesheet->rejection_reason && $timesheet->status === 'rejected')
            <div class="mb-4 rounded-sm border border-danger/30 bg-danger/5 p-3 text-xs text-danger">Rejected: {{ $timesheet->rejection_reason }}</div>
        @endif

        @if($projects->isEmpty())
            <div class="text-sm text-text-muted">You're not assigned to any projects yet — ask an Admin/HR Admin to assign you under Customers &amp; Projects.</div>
        @else
            @if($canEdit)
                <form wire:submit="addLine" class="mb-4 flex flex-wrap items-end gap-3">
                    <select wire:model.live="newProjectId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— project —</option>
                        @foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                    <select wire:model="newActivityId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— activity —</option>
                        @foreach($activities as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                    <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add row</button>
                </form>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-3">Project / Activity</th>
                            <th class="px-2 py-2">Mon</th>
                            <th class="px-2 py-2">Tue</th>
                            <th class="px-2 py-2">Wed</th>
                            <th class="px-2 py-2">Thu</th>
                            <th class="px-2 py-2">Fri</th>
                            <th class="px-2 py-2">Sat</th>
                            <th class="px-2 py-2">Sun</th>
                            <th class="px-2 py-2">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $line)
                            <tr class="border-b border-border last:border-0">
                                <td class="py-2 pr-3 text-text">{{ $line->project->name }} <span class="text-text-muted">· {{ $line->activity->name }}</span></td>
                                @foreach(\App\Models\Timesheet::DAY_COLUMNS as $col)
                                    <td class="px-2 py-2">
                                        <input type="number" step="0.25" min="0" max="24" wire:model="hours.{{ $line->id }}.{{ $col }}" @disabled(! $canEdit) class="w-16 rounded-sm border border-border bg-surface px-2 py-1 text-sm text-text outline-none focus:border-primary disabled:opacity-50">
                                    </td>
                                @endforeach
                                <td class="px-2 py-2 font-mono font-semibold text-text">{{ number_format($line->totalHours(), 2) }}</td>
                                <td class="px-2 py-2">
                                    @if($canEdit)
                                        <button wire:click="deleteLine({{ $line->id }})" class="text-xs font-semibold text-danger">Remove</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if($lines->isEmpty())
                            <tr><td colspan="10" class="py-6 text-center text-text-muted">No rows yet — add one above.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            @if($canEdit && $lines->isNotEmpty())
                <div class="mt-4 flex gap-3">
                    <button wire:click="saveHours" class="rounded-sm border border-border px-4 py-2 text-sm font-semibold text-text hover:bg-bg">Save hours</button>
                    <button wire:click="submit" wire:confirm="Submit this timesheet for approval?" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">{{ $timesheet->status === 'rejected' ? 'Resubmit' : 'Submit for approval' }}</button>
                </div>
            @endif
        @endif
    </section>

    @if($timesheet->actionLogs->isNotEmpty())
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3 font-display text-base font-bold text-text">Action log</h2>
            <div class="divide-y divide-border">
                @foreach($timesheet->actionLogs as $log)
                    <div class="py-2 text-xs text-text-muted">
                        <span class="font-semibold text-text">{{ ucfirst($log->action) }}</span> by {{ $log->actor?->name ?? 'System' }} — {{ $log->created_at->format('j M Y, g:ia') }}
                        @if($log->note) · {{ $log->note }} @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
