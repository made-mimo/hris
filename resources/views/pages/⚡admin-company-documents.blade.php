<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Company Registration Documents">
    <div class="page-header">
        <div>
            <h1>Company Registration Documents</h1>
            <p class="text-muted">Spec Section E5 — company legal/compliance documents, restricted by default.</p>
        </div>
    </div>

    <livewire:company-document-configuration-manager />
</x-layouts.app>
