<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\HrMetricsSnapshot;
use App\Models\LeaveRequest;
use App\Models\Vacancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spec F2: "compute and store daily facts rather than re-aggregating history
 * live at every page load" — the recommended pattern for any "trend over
 * time" reporting need in the system, applied here first for HR headcount/
 * turnover trends.
 */
class HrMetricsService
{
    /** Idempotent per calendar day — safe to run more than once on the same day (re-running the scheduled job, or a manual trigger). */
    public function captureSnapshot(?Carbon $asOf = null): HrMetricsSnapshot
    {
        $asOf = ($asOf ?? now())->startOfDay();
        $windowStart = $asOf->copy()->subDays(30);

        $activeHeadcount = Employee::where('is_gdpr_purged', false)->whereDoesntHave('terminations')->count();

        $newHires = Employee::where('is_gdpr_purged', false)
            ->whereBetween('hire_date', [$windowStart, $asOf])
            ->count();

        $terminations = EmployeeTermination::whereBetween('date', [$windowStart, $asOf])->count();

        // Headcount at the start of the window, reconstructed from today's
        // count plus who has since joined/left it — avoids a second,
        // slower "headcount as of a past date" query.
        $headcountAtStart = $activeHeadcount - $newHires + $terminations;
        $averageHeadcount = ($activeHeadcount + $headcountAtStart) / 2;
        $turnoverRate = $averageHeadcount > 0 ? round(($terminations / $averageHeadcount) * 100, 2) : 0;

        $openRequisitions = Vacancy::where('is_open', true)->count();

        $pendingLeaveRequests = LeaveRequest::whereIn('status', ['pending_manager', 'pending_hr'])->count();

        $averageTenureYears = Employee::where('is_gdpr_purged', false)->whereDoesntHave('terminations')
            ->get()->avg(fn (Employee $e) => $e->hire_date->diffInDays($asOf) / 365.25) ?? 0;

        return HrMetricsSnapshot::updateOrCreate(
            ['snapshot_date' => $asOf->toDateString()],
            [
                'active_headcount' => $activeHeadcount,
                'new_hires_trailing_30d' => $newHires,
                'terminations_trailing_30d' => $terminations,
                'turnover_rate_percent' => $turnoverRate,
                'open_requisitions' => $openRequisitions,
                'pending_leave_requests' => $pendingLeaveRequests,
                'average_tenure_years' => round($averageTenureYears, 2),
            ]
        );
    }

    public function latest(): ?HrMetricsSnapshot
    {
        return HrMetricsSnapshot::latest('snapshot_date')->first();
    }

    /** Spec F2: "latest snapshot plus up to 12 monthly points" — the last snapshot recorded in each of the trailing 12 calendar months. */
    public function monthlyTrend(): Collection
    {
        return HrMetricsSnapshot::orderBy('snapshot_date')
            ->get()
            ->groupBy(fn (HrMetricsSnapshot $s) => $s->snapshot_date->format('Y-m'))
            ->map(fn (Collection $monthRows) => $monthRows->last())
            ->values()
            ->slice(-12)
            ->values();
    }
}
