<?php

use Livewire\Component;

/** Tab shell, same pattern as master-data-manager.blade.php. */
new class extends Component
{
    public string $activeTab = 'reviews';

    public array $tabs = [
        'reviews' => 'Reviews',
        'goals' => 'Goals',
        'tracker' => 'Tracker',
        'feedback' => '360° Feedback',
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

    @if($activeTab === 'reviews')
        <livewire:performance-reviews-manager wire:key="performance-reviews-manager" />
    @elseif($activeTab === 'goals')
        <livewire:goals-manager wire:key="goals-manager" />
    @elseif($activeTab === 'tracker')
        <livewire:performance-tracker-manager wire:key="performance-tracker-manager" />
    @elseif($activeTab === 'feedback')
        <livewire:feedback-cycles-manager wire:key="feedback-cycles-manager" />
    @endif
</div>
