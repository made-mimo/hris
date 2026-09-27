<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-layouts.guest title="Secure your account" headline="Set up two-factor sign-in." subtext="This is required before you can continue — choose the method that works best for you.">
    <x-slot:illustration>
        <svg width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="#D9251E" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
            <path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"></path>
            <path d="m9 12 2 2 4-4"></path>
        </svg>
    </x-slot:illustration>

    <livewire:two-factor-setup />
</x-layouts.guest>
