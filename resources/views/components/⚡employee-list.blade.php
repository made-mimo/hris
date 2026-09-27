<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\SubUnit;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Spec Section B2's "Employee list with advanced search": combined name/
 * employee-ID search, filters for job title/sub-unit (including its
 * subtree)/supervisor/location, and a three-way current/past/both toggle —
 * "status" here means employment status (terminated vs not), since B2's own
 * Employment Status master list isn't yet populated with any demo data to
 * filter distinctly. Kept as a child component per the "inert page + child
 * Livewire component" rule (PLAN.md 4.3) — a full-page SFC with its own
 * wire:model.live hits the full-document-morph defect documented there.
 */
new class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $jobTitleId = null;

    public ?int $subUnitId = null;

    public ?int $supervisorId = null;

    public ?int $locationId = null;

    public string $statusFilter = 'current';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingJobTitleId(): void
    {
        $this->resetPage();
    }

    public function updatingSubUnitId(): void
    {
        $this->resetPage();
    }

    public function updatingSupervisorId(): void
    {
        $this->resetPage();
    }

    public function updatingLocationId(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /** A sub-unit filter includes its whole subtree (spec: "sub-unit (including its subtree)") — walked here since sub_units is a plain adjacency list, not nested-set. */
    protected function subUnitAndDescendantIds(int $id): array
    {
        $ids = [$id];
        $queue = [$id];
        while ($queue) {
            $current = array_shift($queue);
            $children = SubUnit::where('parent_id', $current)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $queue = array_merge($queue, $children);
        }

        return $ids;
    }

    public function with(): array
    {
        $employees = Employee::query()
            ->with(['jobTitle', 'subUnit'])
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('employee_id', 'like', "%{$this->search}%")
            ))
            ->when($this->jobTitleId, fn ($q) => $q->where('job_title_id', $this->jobTitleId))
            ->when($this->subUnitId, fn ($q) => $q->whereIn('sub_unit_id', $this->subUnitAndDescendantIds($this->subUnitId)))
            ->when($this->supervisorId, fn ($q) => $q->where('supervisor_id', $this->supervisorId))
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->when($this->statusFilter === 'current', fn ($q) => $q->whereDoesntHave('terminations'))
            ->when($this->statusFilter === 'past', fn ($q) => $q->whereHas('terminations'))
            ->orderBy('last_name')
            ->paginate(15);

        return [
            'employees' => $employees,
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get(),
            'supervisors' => Employee::whereHas('subordinates')->orderBy('last_name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<section class="card" style="padding:0;overflow:hidden;">
    <div style="padding:16px 18px;border-bottom:1px solid var(--color-border);display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search by name or employee ID"
               style="flex:1;min-width:220px;max-width:320px;padding:9px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:13px;">

        <select wire:model.live="jobTitleId" style="padding:9px 10px;border:1px solid var(--color-border);border-radius:8px;font-size:12.5px;">
            <option value="">All job titles</option>
            @foreach($jobTitles as $jt)<option value="{{ $jt->id }}">{{ $jt->name }}</option>@endforeach
        </select>

        <select wire:model.live="subUnitId" style="padding:9px 10px;border:1px solid var(--color-border);border-radius:8px;font-size:12.5px;">
            <option value="">All departments</option>
            @foreach($subUnits as $su)<option value="{{ $su->id }}">{{ $su->name }}</option>@endforeach
        </select>

        <select wire:model.live="supervisorId" style="padding:9px 10px;border:1px solid var(--color-border);border-radius:8px;font-size:12.5px;">
            <option value="">All supervisors</option>
            @foreach($supervisors as $s)<option value="{{ $s->id }}">{{ $s->fullName() }}</option>@endforeach
        </select>

        <select wire:model.live="locationId" style="padding:9px 10px;border:1px solid var(--color-border);border-radius:8px;font-size:12.5px;">
            <option value="">All locations</option>
            @foreach($locations as $loc)<option value="{{ $loc->id }}">{{ $loc->name }}</option>@endforeach
        </select>

        <div style="display:inline-flex;border:1px solid var(--color-border);border-radius:8px;overflow:hidden;">
            @foreach(['current' => 'Current', 'past' => 'Past', 'both' => 'Both'] as $key => $label)
                <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                    style="padding:8px 12px;font-size:12px;font-weight:600;border:none;cursor:pointer;background:{{ $statusFilter === $key ? 'var(--color-primary)' : 'transparent' }};color:{{ $statusFilter === $key ? '#fff' : 'var(--color-text)' }};">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div style="display:grid;grid-template-columns:130px minmax(0,1fr) minmax(0,1fr) minmax(0,1fr) 110px;gap:12px;padding:10px 18px;border-bottom:1px solid var(--color-border);font-size:11px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
        <span>Employee ID</span><span>Name</span><span>Job title</span><span>Department</span><span>Hired</span>
    </div>

    @forelse($employees as $employee)
        <a href="{{ route('employees.show', $employee) }}" wire:navigate style="display:grid;grid-template-columns:130px minmax(0,1fr) minmax(0,1fr) minmax(0,1fr) 110px;gap:12px;align-items:center;padding:12px 18px;border-bottom:1px solid var(--color-border);text-decoration:none;color:inherit;">
            <span class="font-mono text-muted" style="font-size:12.5px;">{{ $employee->employee_id }}</span>
            <span style="font-size:13.5px;font-weight:600;">{{ $employee->fullName() }}</span>
            <span style="font-size:13px;" class="text-muted">{{ $employee->jobTitleName() ?? '—' }}</span>
            <span style="font-size:13px;" class="text-muted">{{ $employee->departmentName() ?? '—' }}</span>
            <span class="font-mono text-muted" style="font-size:12.5px;">{{ $employee->hire_date->format('j M Y') }}</span>
        </a>
    @empty
        <div class="hint" style="padding:24px 18px;">No employees match this search.</div>
    @endforelse

    <div style="padding:14px 18px;">{{ $employees->links() }}</div>
</section>
