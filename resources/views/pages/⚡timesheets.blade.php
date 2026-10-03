<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="My Timesheets">
    <div class="page-header">
        <div>
            <h1>My Timesheets</h1>
            <p class="text-muted">One timesheet per week, logged against your assigned projects.</p>
        </div>
    </div>

    <livewire:timesheet-editor />
</x-layouts.app>
