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
            <p class="text-muted">The Customer → Project → Activity structure that timesheets are logged against.</p>
        </div>
    </div>

    <livewire:project-admin-manager />
</x-layouts.app>
