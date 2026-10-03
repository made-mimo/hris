<?php

use Livewire\Component;

/**
 * Backlog #11 — shared by two pages with two different permission levels:
 * /admin/pulse-survey-configuration (HR Admin/Admin — all three tabs) and
 * /pulse-surveys/runs (HR Officer — Launch/Results only, no Templates tab,
 * since authoring the question bank stays HR Admin/Admin-only). Which tabs
 * show is computed from the viewer's own permission, not from which route
 * they arrived by, so this stays a single component either way.
 */
new class extends Component
{
    public string $activeTab = 'runs';

    public function mount(): void
    {
        if (auth()->user()->canView('admin.pulse-survey-configuration')) {
            $this->activeTab = 'templates';
        }
    }

    public function getTabsProperty(): array
    {
        $tabs = [];

        if (auth()->user()->canView('admin.pulse-survey-configuration')) {
            $tabs['templates'] = 'Templates';
        }

        $tabs['runs'] = 'Launch a Run';
        $tabs['results'] = 'Runs & Results';

        return $tabs;
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

    @if($activeTab === 'templates' && array_key_exists('templates', $this->tabs))
        <livewire:pulse-survey-template-manager wire:key="pulse-survey-template-manager" />
    @elseif($activeTab === 'runs')
        <livewire:pulse-survey-run-manager wire:key="pulse-survey-run-manager" />
    @elseif($activeTab === 'results')
        <livewire:pulse-survey-results-manager wire:key="pulse-survey-results-manager" />
    @endif
</div>
