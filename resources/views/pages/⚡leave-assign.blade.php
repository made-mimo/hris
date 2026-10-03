<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Assign Leave">
    <div class="page-header">
        <div>
            <h1>Assign Leave</h1>
            <p class="text-muted">Books leave directly, bypassing the approval workflow.</p>
        </div>
    </div>

    <livewire:leave-assign-form />
</x-layouts.app>
