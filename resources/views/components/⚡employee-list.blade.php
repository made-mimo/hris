<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\SubUnit;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * PIM/HRIS alignment §3C item 5 — job title/sub-unit/supervisor/location are
 * multi-select facets behind a collapsible panel (session-backed open state,
 * since an Alpine-only toggle gets reset by Livewire's own re-renders), each
 * showing a count computed from every OTHER active filter — the standard
 * "faceted search" pattern, not merely the raw unfiltered total.
 */
new class extends Component
{
    use WithPagination;

    private const SESSION_KEY = 'employee-list-filters-open';

    public string $search = '';

    /** @var int[] */
    public array $jobTitleIds = [];

    /** @var int[] */
    public array $subUnitIds = [];

    /** @var int[] */
    public array $supervisorIds = [];

    /** @var int[] */
    public array $locationIds = [];

    public string $statusFilter = 'current';

    public bool $filtersOpen = false;

    public function mount(): void
    {
        $this->filtersOpen = (bool) session(self::SESSION_KEY, false);
    }

    public function toggleFilters(): void
    {
        $this->filtersOpen = ! $this->filtersOpen;
        session([self::SESSION_KEY => $this->filtersOpen]);
    }

    public function clearAllFilters(): void
    {
        $this->search = '';
        $this->jobTitleIds = [];
        $this->subUnitIds = [];
        $this->supervisorIds = [];
        $this->locationIds = [];
        $this->statusFilter = 'current';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingJobTitleIds(): void
    {
        $this->resetPage();
    }

    public function updatingSubUnitIds(): void
    {
        $this->resetPage();
    }

    public function updatingSupervisorIds(): void
    {
        $this->resetPage();
    }

    public function updatingLocationIds(): void
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

    /**
     * The shared base query, with every filter applied except whichever
     * facet is named in $excluding — that facet's own count then reflects
     * what every OTHER active filter leaves, the standard faceted-search
     * behaviour ("if I also picked X, how many rows would each Y option
     * still match?").
     */
    private function filteredQuery(?string $excluding = null): Builder
    {
        $allSubUnitIds = collect($this->subUnitIds)
            ->flatMap(fn (int $id) => $this->subUnitAndDescendantIds($id))
            ->unique()
            ->all();

        return Employee::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('employee_id', 'like', "%{$this->search}%")
            ))
            ->when($excluding !== 'jobTitle' && $this->jobTitleIds, fn ($q) => $q->whereIn('job_title_id', $this->jobTitleIds))
            ->when($excluding !== 'subUnit' && $this->subUnitIds, fn ($q) => $q->whereIn('sub_unit_id', $allSubUnitIds))
            ->when($excluding !== 'supervisor' && $this->supervisorIds, fn ($q) => $q->whereIn('supervisor_id', $this->supervisorIds))
            ->when($excluding !== 'location' && $this->locationIds, fn ($q) => $q->whereIn('location_id', $this->locationIds))
            ->when($this->statusFilter === 'current', fn ($q) => $q->whereDoesntHave('terminations'))
            ->when($this->statusFilter === 'past', fn ($q) => $q->whereHas('terminations'));
    }

    /** @return array<int, int> facet value => row count, under every other active filter */
    private function facetCounts(string $column, ?string $excluding): array
    {
        return $this->filteredQuery($excluding)
            ->whereNotNull($column)
            ->selectRaw("{$column} as facet_value, count(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', 'facet_value')
            ->all();
    }

    public function activeFilterCount(): int
    {
        return count($this->jobTitleIds) + count($this->subUnitIds) + count($this->supervisorIds) + count($this->locationIds);
    }

    public function with(): array
    {
        $employees = $this->filteredQuery()
            ->with(['jobTitle', 'subUnit'])
            ->orderBy('last_name')
            ->paginate(15);

        $jobTitleCounts = $this->facetCounts('job_title_id', 'jobTitle');
        $subUnitCounts = $this->facetCounts('sub_unit_id', 'subUnit');
        $supervisorCounts = $this->facetCounts('supervisor_id', 'supervisor');
        $locationCounts = $this->facetCounts('location_id', 'location');

        return [
            'employees' => $employees,
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get()
                ->map(fn ($jt) => ['id' => $jt->id, 'name' => $jt->name, 'count' => $jobTitleCounts[$jt->id] ?? 0]),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get()
                ->map(fn ($su) => ['id' => $su->id, 'name' => $su->name, 'count' => $subUnitCounts[$su->id] ?? 0]),
            'supervisors' => Employee::whereHas('subordinates')->orderBy('last_name')->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->fullName(), 'count' => $supervisorCounts[$s->id] ?? 0]),
            'locations' => Location::where('is_active', true)->orderBy('name')->get()
                ->map(fn ($loc) => ['id' => $loc->id, 'name' => $loc->name, 'count' => $locationCounts[$loc->id] ?? 0]),
            'activeFilterCount' => $this->activeFilterCount(),
            'filterSummary' => $this->buildFilterSummary(),
        ];
    }

    /** A one-line readable summary shown while the panel is collapsed, e.g. "Engineering, IT · Lagos". */
    private function buildFilterSummary(): string
    {
        $parts = [];

        if ($this->jobTitleIds) {
            $parts[] = JobTitle::whereIn('id', $this->jobTitleIds)->pluck('name')->implode(', ');
        }
        if ($this->subUnitIds) {
            $parts[] = SubUnit::whereIn('id', $this->subUnitIds)->pluck('name')->implode(', ');
        }
        if ($this->supervisorIds) {
            $parts[] = Employee::whereIn('id', $this->supervisorIds)->get()->map->fullName()->implode(', ');
        }
        if ($this->locationIds) {
            $parts[] = Location::whereIn('id', $this->locationIds)->pluck('name')->implode(', ');
        }

        return implode(' · ', $parts);
    }
};
?>

<section class="card" style="padding:0;overflow:hidden;">
    <div style="padding:16px 18px;border-bottom:1px solid var(--color-border);display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search by name or employee ID"
               style="flex:1;min-width:220px;max-width:320px;padding:9px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:var(--fs-sm);">

        <button type="button" wire:click="toggleFilters" class="btn btn-outline btn-sm" style="gap:6px;">
            {{ $filtersOpen ? 'Hide filters' : 'Show filters' }}
            @if($activeFilterCount > 0)
                <span class="pill pill-neutral" style="padding:1px 7px;font-size:var(--fs-2xs);">{{ $activeFilterCount }}</span>
            @endif
        </button>

        @if(! $filtersOpen && $filterSummary !== '')
            <span class="text-muted" style="font-size:var(--fs-xs);">{{ $filterSummary }}</span>
        @endif

        @if($activeFilterCount > 0 || $search !== '')
            <button type="button" wire:click="clearAllFilters" class="hint" style="background:none;border:none;cursor:pointer;font-weight:600;">Clear all</button>
        @endif

        <div style="display:inline-flex;border:1px solid var(--color-border);border-radius:8px;overflow:hidden;margin-left:auto;">
            @foreach(['current' => 'Current', 'past' => 'Past', 'both' => 'Both'] as $key => $label)
                <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                    style="padding:8px 12px;font-size:var(--fs-xs);font-weight:600;border:none;cursor:pointer;background:{{ $statusFilter === $key ? 'var(--color-primary)' : 'transparent' }};color:{{ $statusFilter === $key ? '#fff' : 'var(--color-text)' }};">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if($filtersOpen)
        <div style="padding:16px 18px;border-bottom:1px solid var(--color-border);display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;background:var(--color-bg);">
            <div>
                <div class="text-faint" style="font-size:var(--fs-2xs);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Job title</div>
                <div style="max-height:180px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;">
                    @foreach($jobTitles as $jt)
                        <label style="display:flex;align-items:center;gap:7px;font-size:var(--fs-xs);cursor:pointer;{{ $jt['count'] === 0 && ! in_array($jt['id'], $jobTitleIds, true) ? 'opacity:0.45;' : '' }}">
                            <input type="checkbox" wire:model.live="jobTitleIds" value="{{ $jt['id'] }}" style="accent-color:var(--color-primary);">
                            <span style="flex:1;">{{ $jt['name'] }}</span>
                            <span class="text-faint font-mono" style="font-size:var(--fs-2xs);">{{ $jt['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="text-faint" style="font-size:var(--fs-2xs);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Department</div>
                <div style="max-height:180px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;">
                    @foreach($subUnits as $su)
                        <label style="display:flex;align-items:center;gap:7px;font-size:var(--fs-xs);cursor:pointer;{{ $su['count'] === 0 && ! in_array($su['id'], $subUnitIds, true) ? 'opacity:0.45;' : '' }}">
                            <input type="checkbox" wire:model.live="subUnitIds" value="{{ $su['id'] }}" style="accent-color:var(--color-primary);">
                            <span style="flex:1;">{{ $su['name'] }}</span>
                            <span class="text-faint font-mono" style="font-size:var(--fs-2xs);">{{ $su['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="text-faint" style="font-size:var(--fs-2xs);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Supervisor</div>
                <div style="max-height:180px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;">
                    @foreach($supervisors as $s)
                        <label style="display:flex;align-items:center;gap:7px;font-size:var(--fs-xs);cursor:pointer;{{ $s['count'] === 0 && ! in_array($s['id'], $supervisorIds, true) ? 'opacity:0.45;' : '' }}">
                            <input type="checkbox" wire:model.live="supervisorIds" value="{{ $s['id'] }}" style="accent-color:var(--color-primary);">
                            <span style="flex:1;">{{ $s['name'] }}</span>
                            <span class="text-faint font-mono" style="font-size:var(--fs-2xs);">{{ $s['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="text-faint" style="font-size:var(--fs-2xs);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Location</div>
                <div style="max-height:180px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;">
                    @foreach($locations as $loc)
                        <label style="display:flex;align-items:center;gap:7px;font-size:var(--fs-xs);cursor:pointer;{{ $loc['count'] === 0 && ! in_array($loc['id'], $locationIds, true) ? 'opacity:0.45;' : '' }}">
                            <input type="checkbox" wire:model.live="locationIds" value="{{ $loc['id'] }}" style="accent-color:var(--color-primary);">
                            <span style="flex:1;">{{ $loc['name'] }}</span>
                            <span class="text-faint font-mono" style="font-size:var(--fs-2xs);">{{ $loc['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:130px minmax(0,1fr) minmax(0,1fr) minmax(0,1fr) 110px;gap:12px;padding:10px 18px;border-bottom:1px solid var(--color-border);font-size:var(--fs-2xs);font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
        <span>Employee ID</span><span>Name</span><span>Job title</span><span>Department</span><span>Hired</span>
    </div>

    @forelse($employees as $employee)
        <a href="{{ route('employees.show', $employee) }}" wire:navigate style="display:grid;grid-template-columns:130px minmax(0,1fr) minmax(0,1fr) minmax(0,1fr) 110px;gap:12px;align-items:center;padding:12px 18px;border-bottom:1px solid var(--color-border);text-decoration:none;color:inherit;">
            <span class="font-mono text-muted" style="font-size:var(--fs-xs);">{{ $employee->employee_id }}</span>
            <span style="font-size:var(--fs-sm);font-weight:600;">{{ $employee->fullName() }}</span>
            <span style="font-size:var(--fs-sm);" class="text-muted">{{ $employee->jobTitleName() ?? '—' }}</span>
            <span style="font-size:var(--fs-sm);" class="text-muted">{{ $employee->departmentName() ?? '—' }}</span>
            <span class="font-mono text-muted" style="font-size:var(--fs-xs);">{{ $employee->hire_date->format(\App\Support\Dates::DATE) }}</span>
        </a>
    @empty
        <div class="hint" style="padding:24px 18px;">No employees match this search.</div>
    @endforelse

    <div style="padding:14px 18px;">{{ $employees->links() }}</div>
</section>
