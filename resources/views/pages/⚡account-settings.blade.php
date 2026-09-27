<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.app title="Account Settings">
    <div class="page-header">
        <div>
            <h1>Account Settings</h1>
            <p class="text-muted">Password and account security — moved off My Profile per spec review.</p>
        </div>
    </div>

    <div class="grid grid-2" style="align-items:start;">
        <section class="card">
            <div class="card-header"><h2>Change password</h2></div>
            <livewire:change-password />
        </section>

        <livewire:account-security />
    </div>
</x-layouts.app>
