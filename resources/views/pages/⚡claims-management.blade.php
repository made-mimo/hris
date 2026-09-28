<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Claims Management">
    <div class="page-header">
        <div>
            <h1>Claims Management</h1>
            <p class="text-muted">Spec Section E1 — filter, review, and mark expense claims paid.</p>
        </div>
    </div>

    <livewire:claims-manager />
</x-layouts.app>
