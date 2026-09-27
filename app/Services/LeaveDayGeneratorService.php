<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a date range + chosen duration type into the per-day breakdown
 * spec C1 calls for ("one child record per calendar day in the requested
 * range"), using LeaveCalendarService's Work-Week/Holiday-aware resolution
 * instead of the flat "skip weekends" placeholder Section 8 shipped with.
 */
class LeaveDayGeneratorService
{
    /** Matches the pre-existing duration factors this session's Apply Leave screen already used, so totals stay consistent with what was there before this rebuild. */
    private const DURATION_CAP = ['full' => 1.0, 'am' => 0.5, 'pm' => 0.5, 'time' => 0.25];

    public function __construct(private LeaveCalendarService $calendar) {}

    /** @return array{rows: Collection<int, array>, totalDays: float} */
    public function generate(Carbon $start, Carbon $end, string $durationType): array
    {
        $rows = collect();
        $total = 0.0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $resolved = $this->calendar->resolve($d);

            if (! $resolved['isWorkingDay']) {
                $rows->push([
                    'date' => $d->copy(), 'is_working_day' => false, 'duration_type' => 'full',
                    'day_value' => 0, 'status' => 'inert',
                ]);

                continue;
            }

            $dayValue = min($resolved['dayValue'], self::DURATION_CAP[$durationType] ?? 1.0);

            $rows->push([
                'date' => $d->copy(), 'is_working_day' => true, 'duration_type' => $durationType,
                'day_value' => $dayValue, 'status' => 'pending',
            ]);
            $total += $dayValue;
        }

        return ['rows' => $rows, 'totalDays' => $total];
    }
}
