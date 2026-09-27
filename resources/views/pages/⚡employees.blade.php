<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Employees">
    <div class="page-header">
        <div>
            <h1>Employees</h1>
            <p class="text-muted">Spec Section B2 — the Employee Master Record list, with the Employee ID auto-generated on creation.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="{{ route('org-chart') }}" wire:navigate class="btn btn-outline">Org chart</a>
            <a href="{{ route('employees.reports') }}" wire:navigate class="btn btn-outline">Reports</a>
            <a href="{{ route('employees.create') }}" wire:navigate class="btn btn-primary">Add employee</a>
        </div>
    </div>

    <livewire:employee-list />

    <div class="mt-6">
        <livewire:employee-csv-import />
    </div>
</x-layouts.app>
