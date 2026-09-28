<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.guest title="Verify sign-in" headline="One extra step keeps HR records safe." subtext="Trusted devices skip this step until the trust period ends or you revoke it from Account Security.">
    <x-slot:illustration>
        <svg width="400" height="340" viewBox="0 0 400 340" fill="none">
            <rect x="70" y="60" width="200" height="140" rx="10" stroke="#4A4C56" stroke-width="2.5" fill="#1B1B1F"></rect>
            <path d="M50 214h240l-14 18H64z" stroke="#4A4C56" stroke-width="2.5" fill="#1B1B1F" stroke-linejoin="round"></path>
            <rect x="96" y="92" width="92" height="8" rx="4" fill="#34363F"></rect>
            <rect x="96" y="112" width="140" height="8" rx="4" fill="#34363F"></rect>
            <rect x="96" y="140" width="24" height="30" rx="5" fill="#2A2C34" stroke="#D9251E" stroke-width="2"></rect>
            <rect x="128" y="140" width="24" height="30" rx="5" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></rect>
            <rect x="160" y="140" width="24" height="30" rx="5" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></rect>
            <rect x="192" y="140" width="24" height="30" rx="5" fill="#2A2C34" stroke="#4A4C56" stroke-width="2"></rect>
            <rect x="262" y="120" width="76" height="136" rx="12" stroke="#4A4C56" stroke-width="2.5" fill="#14151A"></rect>
            <rect x="284" y="238" width="32" height="4" rx="2" fill="#4A4C56"></rect>
            <path d="M300 146l-20 8v14c0 13 9 21 20 24 11-3 20-11 20-24v-14z" fill="#D9251E"></path>
            <path d="m291 169 6 6 12-13" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M30 300h60l12-26 18 50 14-44 8 20h70" stroke="#D9251E" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"></path>
        </svg>
    </x-slot:illustration>

    <livewire:two-factor-verify />
</x-layouts.guest>
