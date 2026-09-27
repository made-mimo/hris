<?php

use App\Models\Employee;
use App\Models\Setting;
use Livewire\Component;

/**
 * Tab shell for spec B2's "tabbed profile editor" — same lazy-mount-per-tab
 * pattern as master-data-manager (see PLAN.md 4.3/9): safe to carry
 * wire:click directly since this isn't a full-page SFC. Spec B2's
 * "organization-wide toggle to show/hide optional (non-required) profile
 * fields" is implemented here at tab granularity — Job Details, Personal,
 * Contact, Reporting, Termination, and Activity are the "required" core;
 * the rest hide when Settings switches the toggle off.
 */
new class extends Component
{
    public Employee $employee;

    public string $activeTab = 'job';

    public array $optionalTabs = [
        'family' => 'Family',
        'immigration' => 'Immigration',
        'compensation' => 'Compensation',
        'qualifications' => 'Qualifications',
        'career' => 'Career',
        'attachments' => 'Attachments & Custom Fields',
    ];

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function getTabsProperty(): array
    {
        $tabs = [
            'job' => 'Job Details',
            'personal' => 'Personal',
            'contact' => 'Contact',
        ];

        if (Setting::current()->show_optional_profile_fields) {
            $tabs += $this->optionalTabs;
        }

        return $tabs + [
            'reporting' => 'Reporting',
            'termination' => 'Termination',
            'activity' => 'Activity',
        ];
    }
};
?>

<div>
    <div class="mb-4 flex flex-wrap gap-1.5 border-b border-border">
        @foreach($this->tabs as $key => $label)
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
    @elseif($activeTab === 'family' && array_key_exists('family', $this->tabs))
        <livewire:employee-family-tab :employee="$employee" wire:key="employee-family-{{ $employee->id }}" />
    @elseif($activeTab === 'immigration' && array_key_exists('immigration', $this->tabs))
        <livewire:employee-immigration-tab :employee="$employee" wire:key="employee-immigration-{{ $employee->id }}" />
    @elseif($activeTab === 'compensation' && array_key_exists('compensation', $this->tabs))
        <livewire:employee-compensation-tab :employee="$employee" wire:key="employee-compensation-{{ $employee->id }}" />
    @elseif($activeTab === 'qualifications' && array_key_exists('qualifications', $this->tabs))
        <livewire:employee-qualifications-tab :employee="$employee" wire:key="employee-qualifications-{{ $employee->id }}" />
    @elseif($activeTab === 'career' && array_key_exists('career', $this->tabs))
        <livewire:employee-career-tab :employee="$employee" wire:key="employee-career-{{ $employee->id }}" />
    @elseif($activeTab === 'reporting')
        <livewire:employee-reporting-tab :employee="$employee" wire:key="employee-reporting-{{ $employee->id }}" />
    @elseif($activeTab === 'termination')
        <livewire:employee-termination-tab :employee="$employee" wire:key="employee-termination-{{ $employee->id }}" />
    @elseif($activeTab === 'attachments' && array_key_exists('attachments', $this->tabs))
        <livewire:employee-attachments-tab :employee="$employee" wire:key="employee-attachments-{{ $employee->id }}" />
    @elseif($activeTab === 'activity')
        <livewire:employee-activity-tab :employee="$employee" wire:key="employee-activity-{{ $employee->id }}" />
    @endif
</div>
