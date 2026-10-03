<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Recruitment">
    <div class="page-header">
        <div>
            <h1>Recruitment</h1>
            <p class="text-muted">Requisitions, vacancies and the candidate pipeline.</p>
        </div>
    </div>

    <livewire:recruitment-manager />
</x-layouts.app>
