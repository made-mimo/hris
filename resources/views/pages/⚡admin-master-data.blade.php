<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Organization & Master Data">
    <div class="page-header">
        <div>
            <h1>Organization &amp; Master Data</h1>
            <p class="text-muted">The lookup lists and organisation structure used across the system.</p>
        </div>
    </div>

    <livewire:master-data-manager />
</x-layouts.app>
