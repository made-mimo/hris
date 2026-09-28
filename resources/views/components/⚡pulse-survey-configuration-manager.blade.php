<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'templates';

    public array $tabs = [
        'templates' => 'Templates',
        'runs' => 'Launch a Run',
        'results' => 'Runs & Results',
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

    @if($activeTab === 'templates')
        <livewire:pulse-survey-template-manager wire:key="pulse-survey-template-manager" />
    @elseif($activeTab === 'runs')
        <livewire:pulse-survey-run-manager wire:key="pulse-survey-run-manager" />
    @elseif($activeTab === 'results')
        <livewire:pulse-survey-results-manager wire:key="pulse-survey-results-manager" />
    @endif
</div>
