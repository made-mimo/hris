<?php

use App\Models\WorkWeekDay;
use Livewire\Component;

/** Spec C1: "a configurable Work Week pattern (each weekday marked full/half/non-working)." Seeds Mon–Fri full / Sat–Sun non-working on first visit if nothing is configured yet — a sensible default, not a hard-coded assumption baked into LeaveCalendarService itself (which falls back to the same default only when no rows exist at all). */
new class extends Component
{
    public const WEEKDAY_NAMES = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    public function mount(): void
    {
        if (WorkWeekDay::count() === 0) {
            foreach (range(0, 6) as $weekday) {
                WorkWeekDay::create([
                    'weekday' => $weekday,
                    'day_type' => in_array($weekday, [0, 6], true) ? 'non_working' : 'full',
                ]);
            }
        }
    }

    public function setDayType(int $weekday, string $dayType): void
    {
        WorkWeekDay::updateOrCreate(['weekday' => $weekday], ['day_type' => $dayType]);
        session()->flash('status', self::WEEKDAY_NAMES[$weekday].' updated.');
    }

    public function with(): array
    {
        return ['days' => WorkWeekDay::orderBy('weekday')->get()->keyBy('weekday')];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="divide-y divide-border">
            @foreach(self::WEEKDAY_NAMES as $weekday => $label)
                <div class="flex items-center justify-between py-2.5">
                    <span class="text-sm font-medium text-text">{{ $label }}</span>
                    <select wire:change="setDayType({{ $weekday }}, $event.target.value)" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                        @foreach(\App\Models\WorkWeekDay::TYPES as $type)
                            <option value="{{ $type }}" @selected(($days[$weekday]->day_type ?? '') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </section>
</div>
