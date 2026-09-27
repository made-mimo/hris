<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.guest
    title="Update your password"
    headline="Your password policy has changed."
    subtext="Set a new password that meets the current requirements before continuing."
>
    <x-slot:illustration>
        <svg width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="#D9251E" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
            <rect x="4" y="10" width="16" height="11" rx="2"></rect>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
        </svg>
    </x-slot:illustration>

    <h1 style="font-size:28px;">Update your password</h1>
    <p class="text-muted" style="margin:8px 0 24px;font-size:14px;line-height:1.55;">
        Your organization's password requirements have changed since you last set yours. Choose a new one that meets the current policy to continue.
    </p>

    <livewire:change-password :forced="true" />
</x-layouts.guest>
