<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Employee Reports">
    <div class="page-header">
        <div>
            <h1>Employee Reports</h1>
            <p class="text-muted">Spec Section B2 — ad-hoc reporting: choose fields and filters, then export.</p>
        </div>
    </div>

    <livewire:employee-report-builder />
</x-layouts.app>
