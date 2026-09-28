<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Renewal &amp; Compliance Configuration">
    <div class="page-header">
        <div>
            <h1>Renewal &amp; Compliance Configuration</h1>
            <p class="text-muted">Spec Section 3.2 — reminder tiers and notify-targets for Vehicle Renewals, Asset Warranties, and Company Registration Documents.</p>
        </div>
    </div>

    <livewire:renewal-configuration-manager />
</x-layouts.app>
