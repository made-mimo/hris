<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Leave Reports">
    <div class="page-header">
        <div>
            <h1>Leave Reports</h1>
            <p class="text-muted">Spec Section C1 — balance and usage, plus year-end carryover/forfeiture.</p>
        </div>
    </div>

    <livewire:leave-reports-viewer />
</x-layouts.app>
