<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Helpdesk">
    <div class="page-header">
        <div>
            <h1>Helpdesk</h1>
            <p class="text-muted">Support tickets, including confidential Grievance/Whistleblower reports.</p>
        </div>
    </div>

    <livewire:ticket-manager />
</x-layouts.app>
