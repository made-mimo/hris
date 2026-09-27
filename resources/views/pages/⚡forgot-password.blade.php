<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.guest
    title="Reset password"
    headline="Your people, your workday — in one place."
    subtext="Leave, claims, attendance, onboarding and approvals for the Systems Intelligenz team."
>
    <x-slot:illustration>
        <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#D9251E" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
            <rect x="4" y="10" width="16" height="11" rx="2"></rect>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
        </svg>
    </x-slot:illustration>

    <livewire:forgot-password-form />
</x-layouts.guest>
