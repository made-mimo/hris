<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Timesheet Reports">
    <div class="page-header">
        <div>
            <h1>Timesheet Reports</h1>
            <p class="text-muted">Spec Section C2 — time by project, activity, and employee.</p>
        </div>
    </div>

    <livewire:timesheet-reports-viewer />
</x-layouts.app>
