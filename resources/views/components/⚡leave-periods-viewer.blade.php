<?php

use App\Models\LeavePeriod;
use Livewire\Component;

/** Spec C1: "Leave Period configuration (fixed to the calendar year per SI policy) with a history of past configurations so a future change doesn't retroactively alter already-closed periods." Rows are created automatically (current year on demand, future years by the year-end carryover job) — this is a read-only history view, not an editor, since a closed year's dates are exactly what must never be retroactively changed. */
new class extends Component
{
    public function mount(): void
    {
        LeavePeriod::forYear(now()->year);
    }

    public function with(): array
    {
        return ['periods' => LeavePeriod::orderByDesc('year')->get()];
    }
};
?>

<div>
    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3 text-xs text-text-muted">Fixed to the calendar year per SI policy. A past year's row is never edited once closed — the year-end carryover job creates the next year's row automatically.</div>
        <div class="divide-y divide-border">
            @foreach($periods as $period)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <span class="font-medium text-text">{{ $period->year }}</span>
                    <span class="font-mono text-text-muted">{{ $period->starts_on->format('j M Y') }} – {{ $period->ends_on->format('j M Y') }}</span>
                    @if($period->year === now()->year) <span class="rounded-pill bg-accent-light px-2 py-0.5 text-[10px] font-semibold text-accent">Current</span> @endif
                </div>
            @endforeach
        </div>
    </section>
</div>
