<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Policies">
    <div class="page-header">
        <div>
            <h1>Policies</h1>
            <p class="text-muted">Company policy documents and acknowledgements.</p>
        </div>
    </div>

    <livewire:policy-manager />
</x-layouts.app>
