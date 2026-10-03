<?php

use App\Services\AttendanceService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public function toggleClock(AttendanceService $attendance): void
    {
        $me = auth()->user()->employee;

        try {
            if ($me->isClockedIn()) {
                $attendance->punchOut($me, auth()->user());
            } else {
                $attendance->punchIn($me, auth()->user());
            }
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['punch'][0] ?? 'That punch could not be recorded.');
        }
    }

    public function with(): array
    {
        $me = auth()->user()->employee;

        return ['me' => $me, 'currentPunch' => $me->currentPunch()];
    }
};
?>

<div style="display:flex;align-items:center;gap:10px;">
    @if(session('error'))
        <span style="font-size:var(--fs-xs);color:var(--color-danger);">{{ session('error') }}</span>
    @endif
    <span class="pill pill-neutral" style="height:40px;padding:0 14px;">
        <span style="width:8px;height:8px;border-radius:50%;background:{{ $currentPunch ? 'var(--color-accent)' : 'var(--color-text-faint)' }};"></span>
        {{ $currentPunch ? 'Clocked in '.$currentPunch->punch_in_at_local->format('H:i') : 'Not clocked in' }}
    </span>
    <button type="button" wire:click="toggleClock" class="btn btn-primary">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
        {{ $currentPunch ? 'Punch out' : 'Punch in' }}
    </button>
</div>
