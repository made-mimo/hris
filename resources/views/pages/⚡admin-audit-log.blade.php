<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Audit Log">
    <div class="page-header">
        <div>
            <h1>Audit Log</h1>
            <p class="text-muted">Every change to records and every sign-in and two-factor event, in one place.</p>
        </div>
    </div>

    <livewire:audit-log-browser />
</x-layouts.app>
