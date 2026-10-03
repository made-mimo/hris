<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Timesheet Approvals">
    <div class="page-header">
        <div>
            <h1>Timesheet Approvals</h1>
            <p class="text-muted">Submitted timesheets across the employees you can access.</p>
        </div>
    </div>

    <livewire:timesheet-approvals-board />
</x-layouts.app>
