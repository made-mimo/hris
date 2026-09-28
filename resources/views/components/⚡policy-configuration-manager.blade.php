<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'categories';

    public array $tabs = [
        'categories' => 'Categories',
        'documents' => 'Documents & Versions',
        'grants' => 'Restricted-Category Access',
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
        <livewire:policy-category-manager wire:key="policy-category-manager" />
    @elseif($activeTab === 'documents')
        <livewire:policy-document-manager wire:key="policy-document-manager" />
    @elseif($activeTab === 'grants')
        <livewire:policy-access-grant-manager wire:key="policy-access-grant-manager" />
    @endif
</div>
