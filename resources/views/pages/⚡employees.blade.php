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
        <a href="{{ route('employees.create') }}" wire:navigate class="btn btn-primary">Add employee</a>
    </div>

    <livewire:employee-list />
</x-layouts.app>
