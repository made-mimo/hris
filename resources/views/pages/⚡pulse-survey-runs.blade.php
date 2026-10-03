<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Pulse Survey Runs">
    <div class="page-header">
        <div>
            <h1>Pulse Survey Runs</h1>
            <p class="text-muted">Launch a run from an HR Admin-authored template, and review anonymized results.</p>
        </div>
    </div>

    <livewire:pulse-survey-configuration-manager />
</x-layouts.app>
