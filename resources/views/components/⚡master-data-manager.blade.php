<?php

use Livewire\Component;

/**
 * Tab shell for spec Section B1's master-data admin screens. Each tab is its
 * own Livewire child component, mounted only once its @if block goes true —
 * this component itself is safe to carry wire:click directly (it is not a
 * full-page SFC registered via Route::livewire(), so it doesn't hit the
 * full-document-morph defect documented in PLAN.md 4.3 / the "inert page +
 * child" rule; only the page wrapping this one needs to stay inert).
 */
new class extends Component
{
    public string $activeTab = 'job_titles';

    public array $tabs = [
        'job_titles' => 'Job Titles',
        'sub_units' => 'Sub-units',
        'locations' => 'Locations',
        'work_shifts' => 'Work Shifts',
        'pay_grades' => 'Pay Grades',
        'master_lists' => 'Other Lists',
        'provinces' => 'Provinces',
        'custom_fields' => 'Employee Custom Fields',
        'modules' => 'Modules',
    ];
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

    @if($activeTab === 'job_titles')
        <livewire:job-titles-manager wire:key="job-titles-manager" />
    @elseif($activeTab === 'sub_units')
        <livewire:sub-units-manager wire:key="sub-units-manager" />
    @elseif($activeTab === 'locations')
        <livewire:locations-manager wire:key="locations-manager" />
    @elseif($activeTab === 'work_shifts')
        <livewire:work-shifts-manager wire:key="work-shifts-manager" />
    @elseif($activeTab === 'pay_grades')
        <livewire:pay-grades-manager wire:key="pay-grades-manager" />
    @elseif($activeTab === 'master_lists')
        <livewire:master-list-manager wire:key="master-list-manager" />
    @elseif($activeTab === 'provinces')
        <livewire:provinces-manager wire:key="provinces-manager" />
    @elseif($activeTab === 'custom_fields')
        <livewire:employee-custom-fields-manager wire:key="employee-custom-fields-manager" />
    @elseif($activeTab === 'modules')
        <livewire:module-toggles-manager wire:key="module-toggles-manager" />
    @endif
</div>
