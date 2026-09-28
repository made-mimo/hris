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
        <svg width="420" height="360" viewBox="0 0 420 360" fill="none">
            <path d="M210 92v38M90 130h240M90 130v38M210 130v38M330 130v38M90 232v30M50 262h80M50 262v22M130 262v22M330 232v30M290 262h80M290 262v22M370 262v22" stroke="#4A4C56" stroke-width="2.5" stroke-linecap="round"></path>
            <circle cx="210" cy="60" r="32" fill="#D9251E"></circle>
            <circle cx="210" cy="52" r="10" stroke="#FFFFFF" stroke-width="2.5"></circle>
            <path d="M192 78c3-8 10-12 18-12s15 4 18 12" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round"></path>
            <circle cx="90" cy="200" r="30" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></circle>
            <circle cx="90" cy="193" r="9" stroke="#9195A0" stroke-width="2.2"></circle>
            <path d="M74 216c3-7 9-10 16-10s13 3 16 10" stroke="#9195A0" stroke-width="2.2" stroke-linecap="round"></path>
            <circle cx="210" cy="200" r="30" fill="#2A2C34" stroke="#D9251E" stroke-width="2.5"></circle>
            <circle cx="210" cy="193" r="9" stroke="#E4433D" stroke-width="2.2"></circle>
            <path d="M194 216c3-7 9-10 16-10s13 3 16 10" stroke="#E4433D" stroke-width="2.2" stroke-linecap="round"></path>
            <circle cx="330" cy="200" r="30" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></circle>
            <circle cx="330" cy="193" r="9" stroke="#9195A0" stroke-width="2.2"></circle>
            <path d="M314 216c3-7 9-10 16-10s13 3 16 10" stroke="#9195A0" stroke-width="2.2" stroke-linecap="round"></path>
            <circle cx="50" cy="304" r="20" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></circle>
            <circle cx="130" cy="304" r="20" fill="#D9251E" opacity="0.85"></circle>
            <circle cx="290" cy="304" r="20" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></circle>
            <circle cx="370" cy="304" r="20" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></circle>
            <rect x="248" y="34" width="150" height="40" rx="10" fill="#1B1B1F" stroke="#3A3C46"></rect>
            <circle cx="270" cy="54" r="9" fill="#1E9E63"></circle>
            <path d="m265.5 54 3 3 5.5-6" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            <rect x="288" y="47" width="90" height="6" rx="3" fill="#4A4C56"></rect>
            <rect x="288" y="58" width="56" height="5" rx="2.5" fill="#34363F"></rect>
            <path d="M150 346h40l12-26 18 50 14-44 8 20h60" stroke="#D9251E" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"></path>
        </svg>
    </x-slot:illustration>

    <livewire:login-form />
</x-layouts.guest>
