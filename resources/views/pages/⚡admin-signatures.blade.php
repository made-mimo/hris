<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Signature Verification">
    <div class="page-header">
        <div>
            <h1>Signature Verification</h1>
            <p class="text-muted">The evidence trail for every e-signature and digital consent event across all modules.</p>
        </div>
    </div>

    <livewire:signature-verification-browser />
</x-layouts.app>
