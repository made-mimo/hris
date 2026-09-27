<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Org Chart">
    <div class="page-header">
        <div>
            <h1>Org Chart</h1>
            <p class="text-muted">Spec Section B2 — computed live from the reporting-line graph, multi-root, with cycle protection.</p>
        </div>
    </div>

    <livewire:org-chart-viewer />
</x-layouts.app>
