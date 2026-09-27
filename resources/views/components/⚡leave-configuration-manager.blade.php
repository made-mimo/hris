<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'leave_types';

    public array $tabs = [
        'leave_types' => 'Leave Types',
        'holidays' => 'Holidays',
        'work_week' => 'Work Week',
        'leave_periods' => 'Leave Periods',
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

    @if($activeTab === 'leave_types')
        <livewire:leave-types-manager wire:key="leave-types-manager" />
    @elseif($activeTab === 'holidays')
        <livewire:holidays-manager wire:key="holidays-manager" />
    @elseif($activeTab === 'work_week')
        <livewire:work-week-manager wire:key="work-week-manager" />
    @elseif($activeTab === 'leave_periods')
        <livewire:leave-periods-viewer wire:key="leave-periods-viewer" />
    @endif
</div>
