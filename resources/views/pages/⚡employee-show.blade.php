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
        </div>
    </div>

    <livewire:employee-detail-form :employee="$employee" :key="'employee-'.$employee->id" />
</x-layouts.app>
