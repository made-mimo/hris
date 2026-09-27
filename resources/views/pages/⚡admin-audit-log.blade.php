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
            <p class="text-muted">Spec Section 3.2/A2: every entity mutation and every login/2FA security event, in one place.</p>
        </div>
    </div>

    <livewire:audit-log-browser />
</x-layouts.app>
