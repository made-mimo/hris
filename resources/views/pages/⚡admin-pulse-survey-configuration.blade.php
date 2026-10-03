<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Pulse Survey Configuration">
    <div class="page-header">
        <div>
            <h1>Pulse Survey Configuration</h1>
            <p class="text-muted">Survey templates, launching runs and reviewing anonymized results.</p>
        </div>
    </div>

    <livewire:pulse-survey-configuration-manager />
</x-layouts.app>
