<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Asset Configuration">
    <div class="page-header">
        <div>
            <h1>Asset Configuration</h1>
            <p class="text-muted">Asset categories and custom field definitions.</p>
        </div>
    </div>

    <livewire:asset-configuration-manager />
</x-layouts.app>
