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
            <p class="text-muted">Reporting lines, updated live.</p>
        </div>
    </div>

    <livewire:org-chart-viewer />
</x-layouts.app>
