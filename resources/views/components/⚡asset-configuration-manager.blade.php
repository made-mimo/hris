<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'categories';

    public array $tabs = [
        'categories' => 'Asset Categories',
        'fields' => 'Custom Fields',
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

    @if($activeTab === 'categories')
        <livewire:asset-category-manager wire:key="asset-category-manager" />
    @elseif($activeTab === 'fields')
        <livewire:custom-field-manager wire:key="custom-field-manager" :subject-type="'asset'" />
    @endif
</div>
