<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Customers & Projects">
    <div class="page-header">
        <div>
            <h1>Customers &amp; Projects</h1>
            <p class="text-muted">Spec Section C2 — the Customer → Project → Activity hierarchy timesheets log against.</p>
        </div>
    </div>

    <livewire:project-admin-manager />
</x-layouts.app>
