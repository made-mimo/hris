<?php

use App\Models\Employee;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Spec Section B2's "Employee list with advanced search" — the combined
 * name/employee-ID search and pagination this session builds; job title/
 * sub-unit/supervisor/location filters and the current/past/both three-way
 * toggle are follow-up work (see PLAN.md) once those fields are backed by
 * real master-data tables instead of plain strings. Kept as a child
 * component per the "inert page + child Livewire component" rule (PLAN.md
 * 4.3) — a full-page SFC with its own wire:model.live hits the same
 * full-document-morph defect documented there (and already found twice
 * this session on the login form).
 */
new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $employees = Employee::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('employee_id', 'like', "%{$this->search}%")
            ))
            ->orderBy('last_name')
            ->paginate(15);

        return ['employees' => $employees];
    }
};
?>

<section class="card" style="padding:0;overflow:hidden;">
    <div style="padding:16px 18px;border-bottom:1px solid var(--color-border);">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search by name or employee ID"
               style="width:100%;max-width:360px;padding:9px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:13px;">
    </div>

    <div style="display:grid;grid-template-columns:130px minmax(0,1fr) minmax(0,1fr) minmax(0,1fr) 110px;gap:12px;padding:10px 18px;border-bottom:1px solid var(--color-border);font-size:11px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
        <span>Employee ID</span><span>Name</span><span>Job title</span><span>Department</span><span>Hired</span>
    </div>

    @forelse($employees as $employee)
        <a href="{{ route('employees.show', $employee) }}" wire:navigate style="display:grid;grid-template-columns:130px minmax(0,1fr) minmax(0,1fr) minmax(0,1fr) 110px;gap:12px;align-items:center;padding:12px 18px;border-bottom:1px solid var(--color-border);text-decoration:none;color:inherit;">
            <span class="font-mono text-muted" style="font-size:12.5px;">{{ $employee->employee_id }}</span>
            <span style="font-size:13.5px;font-weight:600;">{{ $employee->fullName() }}</span>
            <span style="font-size:13px;" class="text-muted">{{ $employee->job_title ?? '—' }}</span>
            <span style="font-size:13px;" class="text-muted">{{ $employee->department ?? '—' }}</span>
            <span class="font-mono text-muted" style="font-size:12.5px;">{{ $employee->hire_date->format('j M Y') }}</span>
        </a>
    @empty
        <div class="hint" style="padding:24px 18px;">No employees match this search.</div>
    @endforelse

    <div style="padding:14px 18px;">{{ $employees->links() }}</div>
</section>
