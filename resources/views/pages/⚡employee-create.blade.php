<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Add employee">
    <div class="page-header">
        <div>
            <div class="text-muted" style="font-size:var(--fs-sm);margin-bottom:6px;">
                <a href="{{ route('employees') }}" wire:navigate class="text-muted">Employees</a> <span>/</span> <span style="color:var(--color-text);font-weight:600;">Add employee</span>
            </div>
            <h1>Add employee</h1>
            <p class="text-muted">Only the name is required to start. Everything else can be completed later.</p>
        </div>
    </div>

    <livewire:employee-create-form />
</x-layouts.app>
