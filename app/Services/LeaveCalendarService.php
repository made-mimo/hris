<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\WorkWeekDay;
use Carbon\Carbon;

/**
 * Spec C1: resolves whether a given date counts as a working day at all,
 * and at what fraction (full/half), against the configurable Work Week
 * pattern and the Holidays calendar — the calculation every leave-day
 * generation and balance figure in this module is built on, replacing the
 * "just skip weekends" placeholder Section 8's Apply Leave screen shipped
 * with (see PLAN.md).
 */
class LeaveCalendarService
{
    private ?array $workWeek = null;

    private ?array $holidays = null;

    /** @return array{isWorkingDay: bool, dayValue: float} */
    public function resolve(Carbon $date): array
    {
        $holiday = $this->holidayOn($date);
        if ($holiday) {
            return $holiday->length === 'half'
                ? ['isWorkingDay' => true, 'dayValue' => 0.5]
                : ['isWorkingDay' => false, 'dayValue' => 0];
        }

        $dayType = $this->workWeekType($date->dayOfWeek);

        return match ($dayType) {
            'non_working' => ['isWorkingDay' => false, 'dayValue' => 0],
            'half' => ['isWorkingDay' => true, 'dayValue' => 0.5],
            default => ['isWorkingDay' => true, 'dayValue' => 1.0],
        };
    }

    public function isWorkingDay(Carbon $date): bool
    {
        return $this->resolve($date)['isWorkingDay'];
    }

    protected function workWeekType(int $weekday): string
    {
        $this->workWeek ??= WorkWeekDay::pluck('day_type', 'weekday')->all();

        return $this->workWeek[$weekday] ?? ($weekday === 0 || $weekday === 6 ? 'non_working' : 'full');
    }

    protected function holidayOn(Carbon $date): ?Holiday
    {
        $this->holidays ??= Holiday::all()->all();

        foreach ($this->holidays as $holiday) {
            if ($holiday->occursOn($date)) {
                return $holiday;
            }
        }

        return null;
    }
}
