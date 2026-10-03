<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="My Requests">
    <div class="page-header">
        <div>
            <h1>My Requests</h1>
            <p class="text-muted">Every leave request and expense claim you've submitted, and where each one stands.</p>
        </div>
    </div>

    <livewire:my-requests-table />
</x-layouts.app>
