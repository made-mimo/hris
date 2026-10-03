<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Directory">
    <div class="page-header">
        <div>
            <h1>Directory</h1>
            <p class="text-muted">Search for colleagues by name, Employee ID, job title or location.</p>
        </div>
    </div>

    <livewire:directory-search />
</x-layouts.app>
