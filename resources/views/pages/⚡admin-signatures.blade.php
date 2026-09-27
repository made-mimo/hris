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
            <p class="text-muted">Spec Section A8: the evidence trail for every e-signature/digital-consent event across every module.</p>
        </div>
    </div>

    <livewire:signature-verification-browser />
</x-layouts.app>
