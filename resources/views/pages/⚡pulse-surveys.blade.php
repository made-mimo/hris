<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Pulse Surveys">
    <div class="page-header">
        <div>
            <h1>Pulse Surveys</h1>
            <p class="text-muted">Spec Section F8 — quick, anonymous engagement check-ins. Your individual response is never shown to anyone.</p>
        </div>
    </div>

    <livewire:pulse-survey-responder />
</x-layouts.app>
