<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Approvals">
    <div class="page-header">
        <div>
            <h1>Approvals</h1>
            <p class="text-muted">Everything waiting on HR &amp; Admin, oldest first.</p>
        </div>
    </div>

    <livewire:approvals-board />
</x-layouts.app>
