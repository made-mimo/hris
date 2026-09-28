<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Helpdesk Configuration">
    <div class="page-header">
        <div>
            <h1>Helpdesk Configuration</h1>
            <p class="text-muted">Spec Section F4 — ticket categories and confidential-category handler lists.</p>
        </div>
    </div>

    <livewire:helpdesk-configuration-manager />
</x-layouts.app>
