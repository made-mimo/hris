<?php

use App\Models\Employee;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }
};
?>

<x-layouts.app title="{{ $employee->fullName() }}">
    <div class="page-header">
        <div>
            <div class="text-muted" style="font-size:13px;margin-bottom:6px;">
                <a href="{{ route('employees') }}" wire:navigate class="text-muted">Employees</a> <span>/</span> <span style="color:var(--color-text);font-weight:600;">{{ $employee->fullName() }}</span>
            </div>
            <h1>{{ $employee->fullName() }}</h1>
            <p class="text-muted font-mono">{{ $employee->employee_id }}</p>
            @if($employee->isTerminated())
                <span class="mt-1 inline-block rounded-pill bg-danger/10 px-2.5 py-1 text-xs font-semibold text-danger">Terminated</span>
            @endif
        </div>
    </div>

    <livewire:employee-profile-tabs :employee="$employee" :key="'employee-tabs-'.$employee->id" />
</x-layouts.app>
