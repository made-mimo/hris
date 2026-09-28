<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Vehicle Configuration">
    <div class="page-header">
        <div>
            <h1>Vehicle Configuration</h1>
            <p class="text-muted">Spec Section E3 — vehicle custom field definitions.</p>
        </div>
    </div>

    <livewire:custom-field-manager :subject-type="'vehicle'" />
</x-layouts.app>
