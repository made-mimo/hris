<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Spec F3: "a thin, read-only projection of the Employee master, not its
 * own data store" — no new table, just a scoped query. "Terminated
 * employees excluded from browsing by default but still findable by direct
 * name/ID search" — termination inclusion is governed purely by whether a
 * search term is present, independent of the job-title/location filters;
 * purged employees are excluded unconditionally, always.
 */
new class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $jobTitleId = null;

    public ?int $locationId = null;

    public ?int $expandedId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingJobTitleId(): void
    {
        $this->resetPage();
    }

    public function updatingLocationId(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $employees = Employee::query()
            ->with(['jobTitle', 'subUnit', 'location', 'supervisor', 'media'])
            ->where('is_gdpr_purged', false)
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('employee_id', 'like', "%{$this->search}%")
            ))
            ->when(! $this->search, fn ($q) => $q->whereDoesntHave('terminations'))
            ->when($this->jobTitleId, fn ($q) => $q->where('job_title_id', $this->jobTitleId))
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->orderBy('last_name')
            ->paginate(20);

        return [
            'employees' => $employees,
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <div style="flex:1;min-width:220px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Search by name or Employee ID</label>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="e.g. Adaeze or SIL2603001" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Job title</label>
                <select wire:model.live="jobTitleId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All job titles</option>
                    @foreach($jobTitles as $jt)<option value="{{ $jt->id }}">{{ $jt->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Location</label>
                <select wire:model.live="locationId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All locations</option>
                    @foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                </select>
            </div>
        </div>
        @if(! $search)
            <div class="mt-2 text-xs text-text-muted">Showing current employees only. Search by name or Employee ID to also find former employees.</div>
        @endif
    </section>

    {{-- align-items:start — the default CSS Grid stretch makes every card in
         a row match the tallest one, so expanding one employee's detail
         block visually grew every other card in that same row too, as if
         they had all expanded. Each card now sizes to its own content. --}}
    <div class="grid grid-3" style="gap:12px;align-items:start;">
        @foreach($employees as $employee)
            <section class="rounded-md border border-border bg-surface p-4 shadow-sm">
                <button wire:click="$set('expandedId', {{ $expandedId === $employee->id ? 'null' : $employee->id }})" class="flex w-full items-start gap-3 text-left">
                    <div class="avatar" style="width:44px;height:44px;font-size:var(--fs-base);flex-shrink:0;overflow:hidden;">
                        @if($employee->avatarUrl())
                            <img src="{{ $employee->avatarUrl() }}" alt="{{ $employee->fullName() }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            {{ $employee->initials }}
                        @endif
                    </div>
                    <div>
                        <div class="font-display text-sm font-bold text-text">{{ $employee->fullName() }}</div>
                        <div class="text-xs text-text-muted">{{ $employee->jobTitleName() ?? '—' }}</div>
                        <div class="text-xs text-text-muted">{{ $employee->departmentName() ?? '—' }} · {{ $employee->locationName() ?? '—' }}</div>
                        @if($employee->isTerminated())
                            <span class="mt-1 inline-block rounded-pill bg-text-faint/15 px-2 py-0.5 text-xs font-semibold text-text-muted">Former employee</span>
                        @endif
                    </div>
                </button>
                @if($expandedId === $employee->id)
                    <div class="mt-2.5 border-t border-border pt-2.5 text-xs text-text-muted">
                        <div>Work email: {{ $employee->work_email ?? '—' }}</div>
                        <div>Phone: {{ $employee->phone_mobile ?? '—' }}</div>
                        <div>Reports to: {{ $employee->supervisor?->fullName() ?? '—' }}</div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($employees->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm" style="grid-column:1/-1;">No employees match your search.</div>
        @endif
    </div>

    <div>{{ $employees->links() }}</div>
</div>
