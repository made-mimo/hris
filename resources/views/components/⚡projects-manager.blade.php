<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectAssignment;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public ?int $customerId = null;

    public ?int $expandedId = null;

    public string $activityName = '';

    public ?int $copyFromProjectId = null;

    public ?int $assignEmployeeId = null;

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'customerId' => ['required', 'exists:customers,id'],
        ]);

        $project = Project::create(['name' => $this->name, 'customer_id' => $this->customerId]);
        $this->reset('name', 'customerId');
        $this->expandedId = $project->id;
        session()->flash('status', 'Project added.');
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        $this->reset('activityName', 'copyFromProjectId', 'assignEmployeeId');
    }

    public function addActivity(): void
    {
        $this->validate(['activityName' => ['required', 'string', 'max:150']]);

        ProjectActivity::create(['project_id' => $this->expandedId, 'name' => $this->activityName]);
        $this->reset('activityName');
    }

    public function deleteActivity(int $id): void
    {
        ProjectActivity::findOrFail($id)->delete();
    }

    /** Spec C2: "ability to copy a full activity list from one project to another when setting up a similar project." */
    public function copyActivities(): void
    {
        $this->validate(['copyFromProjectId' => ['required', 'exists:projects,id']]);

        $source = Project::findOrFail($this->copyFromProjectId);
        $existingNames = ProjectActivity::where('project_id', $this->expandedId)->pluck('name')->all();

        foreach ($source->activities as $activity) {
            if (! in_array($activity->name, $existingNames, true)) {
                ProjectActivity::create(['project_id' => $this->expandedId, 'name' => $activity->name]);
            }
        }

        $this->reset('copyFromProjectId');
        session()->flash('status', "Activities copied from \"{$source->name}\".");
    }

    public function assignEmployee(): void
    {
        $this->validate(['assignEmployeeId' => ['required', 'exists:employees,id']]);

        ProjectAssignment::firstOrCreate(['project_id' => $this->expandedId, 'employee_id' => $this->assignEmployeeId]);
        $this->reset('assignEmployeeId');
    }

    public function toggleAdministrator(int $assignmentId): void
    {
        $assignment = ProjectAssignment::findOrFail($assignmentId);
        $assignment->update(['is_administrator' => ! $assignment->is_administrator]);
    }

    public function unassignEmployee(int $assignmentId): void
    {
        ProjectAssignment::findOrFail($assignmentId)->delete();
    }

    public function delete(int $id): void
    {
        $project = Project::findOrFail($id);
        $project->delete();
        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
        session()->flash('status', 'Project deleted.');
    }

    public function with(): array
    {
        $expandedProject = $this->expandedId ? Project::with(['activities', 'assignments.employee'])->find($this->expandedId) : null;

        return [
            'projects' => Project::with('customer')->withCount('activities')->orderBy('name')->get(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'expandedProject' => $expandedProject,
            'otherProjects' => $expandedProject ? Project::where('id', '!=', $expandedProject->id)->orderBy('name')->get() : collect(),
            'employees' => $expandedProject ? Employee::whereNotIn('id', $expandedProject->assignments->pluck('employee_id'))->orderBy('last_name')->get() : collect(),
        ];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="create" class="mb-4 flex items-end gap-3">
            <div class="flex-1">
                <label class="mb-1.5 block text-xs font-semibold text-text">Project name</label>
                <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Customer</label>
                <select wire:model="customerId" class="rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                    <option value="">— select —</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('name') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('customerId') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="flex flex-col gap-3">
            @foreach($projects as $project)
                <div class="rounded-sm border border-border p-3.5">
                    <div class="flex items-center justify-between">
                        <button wire:click="toggleExpand({{ $project->id }})" class="flex items-center gap-2.5 text-left">
                            <span class="text-sm font-semibold text-text">{{ $project->name }}</span>
                            <span class="text-xs text-text-muted">{{ $project->customer->name }}</span>
                            <span class="text-xs text-text-faint">{{ $project->activities_count }} activit{{ $project->activities_count === 1 ? 'y' : 'ies' }}</span>
                        </button>
                        <button wire:click="delete({{ $project->id }})" wire:confirm="Delete this project and all its activities/assignments?" class="text-xs font-semibold text-danger">Delete</button>
                    </div>

                    @if($expandedProject && $expandedProject->id === $project->id)
                        <div class="mt-3 grid gap-4 border-t border-border pt-3 md:grid-cols-2">
                            <div>
                                <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">Activities</h4>
                                <div class="mb-2 flex flex-col gap-1.5">
                                    @foreach($expandedProject->activities as $activity)
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-text">{{ $activity->name }}</span>
                                            <button wire:click="deleteActivity({{ $activity->id }})" class="font-semibold text-danger">Remove</button>
                                        </div>
                                    @endforeach
                                    @if($expandedProject->activities->isEmpty())
                                        <div class="text-xs text-text-muted">No activities yet.</div>
                                    @endif
                                </div>
                                <form wire:submit="addActivity" class="flex gap-2">
                                    <input type="text" wire:model="activityName" placeholder="New activity" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    <button type="submit" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
                                </form>
                                @if($otherProjects->isNotEmpty())
                                    <div class="mt-2 flex gap-2">
                                        <select wire:model="copyFromProjectId" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                            <option value="">— copy activities from —</option>
                                            @foreach($otherProjects as $op)<option value="{{ $op->id }}">{{ $op->name }}</option>@endforeach
                                        </select>
                                        <button wire:click="copyActivities" class="rounded-sm border border-border px-3 py-1.5 text-xs font-semibold text-primary">Copy</button>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">Assigned employees</h4>
                                <div class="mb-2 flex flex-col gap-1.5">
                                    @foreach($expandedProject->assignments as $assignment)
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-text">{{ $assignment->employee->fullName() }} {{ $assignment->is_administrator ? '(admin)' : '' }}</span>
                                            <span class="flex gap-2">
                                                <button wire:click="toggleAdministrator({{ $assignment->id }})" class="font-semibold text-primary">{{ $assignment->is_administrator ? 'Revoke admin' : 'Make admin' }}</button>
                                                <button wire:click="unassignEmployee({{ $assignment->id }})" class="font-semibold text-danger">Remove</button>
                                            </span>
                                        </div>
                                    @endforeach
                                    @if($expandedProject->assignments->isEmpty())
                                        <div class="text-xs text-text-muted">No one assigned yet.</div>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <select wire:model="assignEmployeeId" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <option value="">— select employee —</option>
                                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                                    </select>
                                    <button wire:click="assignEmployee" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Assign</button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
            @if($projects->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No projects yet.</div>
            @endif
        </div>
    </section>
</div>
