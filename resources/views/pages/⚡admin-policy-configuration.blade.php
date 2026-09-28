<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Policy Configuration">
    <div class="page-header">
        <div>
            <h1>Policy Configuration</h1>
            <p class="text-muted">Spec Section E4 — policy categories, documents, versions, and restricted-category access grants.</p>
        </div>
    </div>

    <livewire:policy-configuration-manager />
</x-layouts.app>
