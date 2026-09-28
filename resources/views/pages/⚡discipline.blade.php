<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Discipline Cases">
    <div class="page-header">
        <div>
            <h1>Discipline Cases</h1>
            <p class="text-muted">Spec Section D3 — restricted to HR Admin and above, plus the employees and supervisors directly involved.</p>
        </div>
    </div>

    <livewire:discipline-manager />
</x-layouts.app>
