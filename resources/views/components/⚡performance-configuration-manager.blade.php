<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'kpis';

    public array $tabs = [
        'kpis' => 'KPIs',
        'templates' => '360° Feedback Templates',
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

    @if($activeTab === 'kpis')
        <livewire:kpi-manager wire:key="kpi-manager" />
    @elseif($activeTab === 'templates')
        <livewire:feedback-template-manager wire:key="feedback-template-manager" />
    @endif
</div>
