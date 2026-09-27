<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'customers';
};
?>

<div>
    <div class="mb-4 flex flex-wrap gap-1.5 border-b border-border">
        @foreach(['customers' => 'Customers', 'projects' => 'Projects'] as $key => $label)
            <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                class="rounded-t-sm border-b-2 px-3.5 py-2.5 text-sm font-semibold transition-colors {{ $activeTab === $key ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($activeTab === 'customers')
        <livewire:customers-manager wire:key="customers-manager" />
    @elseif($activeTab === 'projects')
        <livewire:projects-manager wire:key="projects-manager" />
    @endif
</div>
