<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.guest
    title="Sign in"
    headline="Your people, your workday — in one place."
    subtext="Leave, claims, attendance, onboarding and approvals for the Systems Intelligenz team."
>
    <x-slot:illustration>
        <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#D9251E" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
            <circle cx="12" cy="7" r="3.4"></circle>
            <path d="M4.5 20c1.4-4.3 4-6.5 7.5-6.5s6.1 2.2 7.5 6.5"></path>
        </svg>
    </x-slot:illustration>

    <livewire:login-form />
</x-layouts.guest>
