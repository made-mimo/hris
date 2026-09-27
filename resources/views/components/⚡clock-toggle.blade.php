<?php

use Livewire\Component;

new class extends Component
{
    public function toggleClock(): void
    {
        $me = auth()->user()->employee;
        $me->update([
            'clocked_in' => ! $me->clocked_in,
            'clocked_in_at' => ! $me->clocked_in ? now() : null,
        ]);
    }

    public function with(): array
    {
        return ['me' => auth()->user()->employee];
    }
};
?>

<div style="display:flex;align-items:center;gap:10px;">
    <span class="pill pill-neutral" style="height:40px;padding:0 14px;">
        <span style="width:8px;height:8px;border-radius:50%;background:{{ $me->clocked_in ? 'var(--color-accent)' : 'var(--color-text-faint)' }};"></span>
        {{ $me->clocked_in ? 'Clocked in '.$me->clocked_in_at->clone()->setTimezone(auth()->user()->displayTimezone())->format('H:i') : 'Not clocked in' }}
    </span>
    <button type="button" wire:click="toggleClock" class="btn btn-primary">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
        {{ $me->clocked_in ? 'Punch out' : 'Punch in' }}
    </button>
</div>
