<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Attendance">
    <div class="page-header">
        <div>
            <h1>Attendance</h1>
            <p class="text-muted">Spec Section C3 — punch history, with Admin-configurable self-edit and supervisor proxy-punch permissions.</p>
        </div>
    </div>

    <livewire:attendance-manager />
</x-layouts.app>
