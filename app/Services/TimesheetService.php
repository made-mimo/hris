<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ProjectActivity;
use App\Models\Timesheet;
use App\Models\TimesheetLine;
use Carbon\Carbon;

/** Spec C2: "One timesheet per employee per configured weekly period, auto-resolved from any date." Week = Monday–Sunday, matching the app's existing default (leave-apply-form's own date defaults already use Carbon's Monday-start week). */
class TimesheetService
{
    /** @return array{0: Carbon, 1: Carbon} */
    public function resolveWeek(Carbon $anyDateInWeek): array
    {
        $start = $anyDateInWeek->copy()->startOfWeek(Carbon::MONDAY);

        return [$start, $start->copy()->addDays(6)];
    }

    public function findOrCreateForEmployee(Employee $employee, Carbon $anyDateInWeek): Timesheet
    {
        [$start, $end] = $this->resolveWeek($anyDateInWeek);

        return Timesheet::firstOrCreate(
            ['employee_id' => $employee->id, 'week_start_date' => $start->toDateString()],
            ['week_end_date' => $end->toDateString(), 'status' => 'not_submitted']
        );
    }

    /** Spec: "referential validation that a logged activity actually belongs to its declared project" + "Duplicate-row prevention... existing rows are updated in place." */
    public function upsertLine(Timesheet $timesheet, int $projectId, int $activityId, array $hoursByDay): TimesheetLine
    {
        ProjectActivity::where('id', $activityId)->where('project_id', $projectId)->firstOrFail();

        return TimesheetLine::updateOrCreate(
            ['timesheet_id' => $timesheet->id, 'project_id' => $projectId, 'project_activity_id' => $activityId],
            $hoursByDay
        );
    }
}
