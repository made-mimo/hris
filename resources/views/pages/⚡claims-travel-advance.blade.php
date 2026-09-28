<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Travel Advance">
    <div class="page-header">
        <div>
            <h1>Travel Advance</h1>
            <p class="text-muted">Spec Section E1 — request an advance ahead of a trip; it's reconciled automatically against the expense claim(s) you later submit for the same event.</p>
        </div>
    </div>

    <livewire:travel-advance-manager />
</x-layouts.app>
