<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Onboarding/Offboarding Templates">
    <div class="page-header">
        <div>
            <h1>Onboarding/Offboarding Templates</h1>
            <p class="text-muted">Reusable, ordered checklists; apply one to an employee from their Career tab.</p>
        </div>
    </div>

    <livewire:onboarding-templates-manager />
</x-layouts.app>
