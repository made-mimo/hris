<?php

use Livewire\Component;

/** Tab shell, same pattern as master-data-manager.blade.php — safe to carry wire:click directly since it isn't a full-page SFC. */
new class extends Component
{
    public string $activeTab = 'pipeline';

    public array $tabs = [
        'pipeline' => 'Candidate Pipeline',
        'vacancies' => 'Vacancies',
        'requisitions' => 'Requisitions',
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

    @if($activeTab === 'pipeline')
        <livewire:candidate-pipeline-board wire:key="candidate-pipeline-board" />
    @elseif($activeTab === 'vacancies')
        <livewire:vacancies-manager wire:key="vacancies-manager" />
    @elseif($activeTab === 'requisitions')
        <livewire:requisitions-manager wire:key="requisitions-manager" />
    @endif
</div>
