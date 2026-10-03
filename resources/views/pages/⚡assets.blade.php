<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Assets">
    <div class="page-header">
        <div>
            <h1>Assets</h1>
            <p class="text-muted">Company asset inventory, assignment history, warranties and maintenance log.</p>
        </div>
    </div>

    <livewire:asset-manager />
</x-layouts.app>
