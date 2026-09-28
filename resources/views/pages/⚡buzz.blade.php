<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Buzz">
    <div class="page-header">
        <div>
            <h1>Buzz</h1>
            <p class="text-muted">Spec Section F1 — the company-wide social feed.</p>
        </div>
    </div>

    <livewire:buzz-feed />
</x-layouts.app>
