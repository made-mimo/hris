<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Attendance Reports">
    <div class="page-header">
        <div>
            <h1>Attendance Reports</h1>
            <p class="text-muted">Attendance summary across employees and date ranges.</p>
        </div>
    </div>

    <livewire:attendance-reports-viewer />
</x-layouts.app>
