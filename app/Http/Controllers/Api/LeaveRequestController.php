<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\PermissionService;
use Illuminate\Http\Request;

/**
 * Spec Section 3.2's REST API framework, applied to Leave Management (C1)
 * as the representative slice this Phase 0 service is built and proven
 * against — the resource/collection convention (list w/ pagination/sort/
 * filter, show, create, update, bulk-delete-by-id-array) every other
 * module's API controller should follow, not a one-off.
 *
 * Authorization reuses PermissionService::scopeFor() — the same data-group
 * scope (all/self_subordinates/self/none) the web app's RBAC matrix already
 * grants per role, rather than a parallel API-only permission model.
 */
class LeaveRequestController extends ApiController
{
    public function index(Request $request, PermissionService $permissions)
    {
        $query = $this->scopedQuery($request, $permissions);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $sort = $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (in_array($column, ['start_date', 'end_date', 'days', 'status', 'created_at'], true)) {
            $query->orderBy($column, $direction);
        }

        $paginated = $query->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->success(
            LeaveRequestResource::collection($paginated->items()),
            [
                'page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        );
    }

    public function show(Request $request, LeaveRequest $leaveRequest, PermissionService $permissions)
    {
        $this->authorizeRecord($request, $leaveRequest, $permissions);

        return $this->success(new LeaveRequestResource($leaveRequest));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = $request->user()->employee;

        abort_unless($employee, 422, 'This account has no employee record to submit leave against.');

        $leaveRequest = LeaveRequest::create([
            'reference' => 'LV-'.now()->year.'-'.str_pad((string) (LeaveRequest::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_type' => 'full',
            'days' => now()->parse($data['start_date'])->diffInDays(now()->parse($data['end_date'])) + 1,
            'reason' => $data['reason'] ?? null,
            'status' => $employee->supervisor_id ? 'pending_manager' : 'pending_hr',
            'manager_approved_at' => $employee->supervisor_id ? null : now(),
        ]);

        return $this->success(new LeaveRequestResource($leaveRequest), status: 201);
    }

    public function update(Request $request, LeaveRequest $leaveRequest, PermissionService $permissions)
    {
        $this->authorizeRecord($request, $leaveRequest, $permissions);

        abort_unless($leaveRequest->status === 'pending_manager' || $leaveRequest->status === 'pending_hr', 403, 'Only a request still awaiting approval can be edited.');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $leaveRequest->update($data);

        return $this->success(new LeaveRequestResource($leaveRequest));
    }

    /** Bulk-by-id-array convention (spec 3.2) — cancels every id the caller may act on, ignoring the rest. */
    public function destroy(Request $request, PermissionService $permissions)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $cancelled = [];

        foreach (LeaveRequest::whereIn('id', $data['ids'])->get() as $leaveRequest) {
            if ($this->canAccessRecord($request, $leaveRequest, $permissions) && in_array($leaveRequest->status, ['pending_manager', 'pending_hr'], true)) {
                $leaveRequest->update(['status' => 'cancelled']);
                $cancelled[] = $leaveRequest->id;
            }
        }

        return $this->success(['cancelled' => $cancelled]);
    }

    private function scopedQuery(Request $request, PermissionService $permissions)
    {
        $user = $request->user();
        $scope = $permissions->scopeFor($user, 'leave_requests');
        $employee = $user->employee;

        return match ($scope) {
            'all' => LeaveRequest::query(),
            'self_subordinates' => LeaveRequest::whereIn('employee_id', $this->subordinateIds($employee)),
            'self' => LeaveRequest::where('employee_id', $employee?->id ?? 0),
            default => LeaveRequest::whereRaw('1 = 0'),
        };
    }

    private function canAccessRecord(Request $request, LeaveRequest $leaveRequest, PermissionService $permissions): bool
    {
        $user = $request->user();
        $scope = $permissions->scopeFor($user, 'leave_requests');
        $employee = $user->employee;

        return match ($scope) {
            'all' => true,
            'self_subordinates' => in_array($leaveRequest->employee_id, $this->subordinateIds($employee), true),
            'self' => $employee && $leaveRequest->employee_id === $employee->id,
            default => false,
        };
    }

    private function authorizeRecord(Request $request, LeaveRequest $leaveRequest, PermissionService $permissions): void
    {
        abort_unless($this->canAccessRecord($request, $leaveRequest, $permissions), 403, 'You do not have access to this leave request.');
    }

    private function subordinateIds(?Employee $employee): array
    {
        if (! $employee) {
            return [];
        }

        return [$employee->id, ...$employee->subordinates()->pluck('id')->all()];
    }
}
