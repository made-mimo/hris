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
            <p class="text-muted">Reminder schedules and who is notified for vehicle renewals, asset warranties and company registration documents.</p>
        </div>
    </div>

    <livewire:renewal-configuration-manager />
</x-layouts.app>
