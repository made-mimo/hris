<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Vehicles">
    <div class="page-header">
        <div>
            <h1>Vehicles</h1>
            <p class="text-muted">Spec Section E3 — company vehicle fleet, assignment history, renewals, and fuel/mileage log.</p>
        </div>
    </div>

    <livewire:vehicle-manager />
</x-layouts.app>
