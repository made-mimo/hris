<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Apply for leave">
    <div class="page-header">
        <div>
            <div class="text-muted" style="font-size:13px;margin-bottom:6px;">
                <a href="{{ route('home') }}" wire:navigate class="text-muted">Home</a> <span>/</span> My Leave <span>/</span> <span style="color:var(--color-text);font-weight:600;">Apply</span>
            </div>
            <h1>Apply for leave</h1>
        </div>
    </div>

    <livewire:leave-apply-form />
</x-layouts.app>
