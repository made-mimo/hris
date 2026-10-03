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
            <p class="text-muted">Your punch history. Self-editing and supervisor punching depend on company settings.</p>
        </div>
    </div>

    <livewire:attendance-manager />
</x-layouts.app>
