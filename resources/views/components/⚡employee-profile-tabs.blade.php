<?php

use App\Models\Employee;
use Livewire\Component;

/** Tab shell for spec B2's "tabbed profile editor" — same lazy-mount-per-tab pattern as master-data-manager (see PLAN.md 4.3/9): safe to carry wire:click directly since this isn't a full-page SFC. */
new class extends Component
{
    public Employee $employee;

    public string $activeTab = 'job';

    public array $tabs = [
        'job' => 'Job Details',
        'personal' => 'Personal',
        'contact' => 'Contact',
        'family' => 'Family',
        'immigration' => 'Immigration',
        'compensation' => 'Compensation',
        'qualifications' => 'Qualifications',
        'reporting' => 'Reporting',
        'termination' => 'Termination',
        'attachments' => 'Attachments & Custom Fields',
        'activity' => 'Activity',
    ];

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }
};
?>

<div>
    <div class="mb-4 flex flex-wrap gap-1.5 border-b border-border">
        @foreach($tabs as $key => $label)
            <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                class="rounded-t-sm border-b-2 px-3.5 py-2.5 text-sm font-semibold transition-colors {{ $activeTab === $key ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($activeTab === 'job')
        <livewire:employee-detail-form :employee="$employee" wire:key="employee-job-{{ $employee->id }}" />
    @elseif($activeTab === 'personal')
        <livewire:employee-personal-tab :employee="$employee" wire:key="employee-personal-{{ $employee->id }}" />
    @elseif($activeTab === 'contact')
        <livewire:employee-contact-tab :employee="$employee" wire:key="employee-contact-{{ $employee->id }}" />
    @elseif($activeTab === 'family')
        <livewire:employee-family-tab :employee="$employee" wire:key="employee-family-{{ $employee->id }}" />
    @elseif($activeTab === 'immigration')
        <livewire:employee-immigration-tab :employee="$employee" wire:key="employee-immigration-{{ $employee->id }}" />
    @elseif($activeTab === 'compensation')
        <livewire:employee-compensation-tab :employee="$employee" wire:key="employee-compensation-{{ $employee->id }}" />
    @elseif($activeTab === 'qualifications')
        <livewire:employee-qualifications-tab :employee="$employee" wire:key="employee-qualifications-{{ $employee->id }}" />
    @elseif($activeTab === 'reporting')
        <livewire:employee-reporting-tab :employee="$employee" wire:key="employee-reporting-{{ $employee->id }}" />
    @elseif($activeTab === 'termination')
        <livewire:employee-termination-tab :employee="$employee" wire:key="employee-termination-{{ $employee->id }}" />
    @elseif($activeTab === 'attachments')
        <livewire:employee-attachments-tab :employee="$employee" wire:key="employee-attachments-{{ $employee->id }}" />
    @elseif($activeTab === 'activity')
        <livewire:employee-activity-tab :employee="$employee" wire:key="employee-activity-{{ $employee->id }}" />
    @endif
</div>
