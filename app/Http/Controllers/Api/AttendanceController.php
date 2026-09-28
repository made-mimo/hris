<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

/** Spec F6: "attendance/time clock" — Punch In/Out is one of spec 3.4's named ESS quick-access actions. */
class AttendanceController extends ApiController
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $paginated = AttendanceRecord::where('employee_id', $employee->id)
            ->latest('punch_in_at_utc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->success(
            AttendanceRecordResource::collection($paginated->items()),
            ['page' => $paginated->currentPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()]
        );
    }

    public function current(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $open = $employee->currentPunch();

        return $this->success($open ? new AttendanceRecordResource($open) : null);
    }

    public function punchIn(Request $request, AttendanceService $attendance)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $record = $attendance->punchIn($employee, $request->user());

        return $this->success(new AttendanceRecordResource($record), status: 201);
    }

    public function punchOut(Request $request, AttendanceService $attendance)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $record = $attendance->punchOut($employee, $request->user());

        return $this->success(new AttendanceRecordResource($record));
    }
}
