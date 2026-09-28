<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ExpenseClaimResource;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Services\ExpenseClaimService;
use App\Services\PermissionService;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;

/**
 * Spec F6: "expense claims." Same resource/collection/authorization
 * conventions as LeaveRequestController (the spec's own "representative
 * slice"), reusing ExpenseClaimService::submit() rather than duplicating
 * the reference/status/notification logic that already lives there.
 */
class ExpenseClaimController extends ApiController
{
    public function index(Request $request, PermissionService $permissions)
    {
        $query = $this->scopedQuery($request, $permissions)->with('lines');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $sort = $request->query('sort', '-submitted_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (in_array($column, ['submitted_at', 'status', 'created_at'], true)) {
            $query->orderBy($column, $direction);
        }

        $paginated = $query->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->success(
            ExpenseClaimResource::collection($paginated->items()),
            ['page' => $paginated->currentPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()]
        );
    }

    public function show(Request $request, ExpenseClaim $expenseClaim, PermissionService $permissions)
    {
        $this->authorizeRecord($request, $expenseClaim, $permissions);

        return $this->success(new ExpenseClaimResource($expenseClaim->load('lines')));
    }

    public function store(Request $request, ExpenseClaimService $claims)
    {
        $data = $request->validate([
            'claim_event_id' => ['required', 'exists:claim_events,id'],
            'currency' => ['required', 'string', 'size:3'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.type_id' => ['required', 'exists:expense_types,id'],
            'lines.*.date' => ['required', 'date'],
            'lines.*.note' => ['nullable', 'string', 'max:500'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record to submit a claim against.');

        $claim = $claims->submit($employee, $data['claim_event_id'], $data['currency'], $data['lines']);

        return $this->success(new ExpenseClaimResource($claim->load('lines')), status: 201);
    }

    /** Spec F6: "approval queues for Supervisor/HR" — mirrors ⚡approvals-board.blade.php's approve() exactly, including the amount-based high-value routing (spec E1). */
    public function approve(Request $request, ExpenseClaim $expenseClaim, WorkflowEngine $engine)
    {
        $fromStatus = $expenseClaim->status;

        $action = ($fromStatus === 'pending_hr' && $expenseClaim->requiresSecondApproval())
            ? 'approve_high_value'
            : 'approve';

        $engine->apply('expense_claim', $expenseClaim, $request->user(), $action);

        $expenseClaim->update(match ($fromStatus) {
            'pending_manager' => ['manager_approved_by' => $request->user()->id, 'manager_approved_at' => now()],
            'pending_second_approval' => ['second_approved_by' => $request->user()->id, 'second_approved_at' => now()],
            default => ['hr_approved_by' => $request->user()->id, 'hr_approved_at' => now()],
        });

        return $this->success(new ExpenseClaimResource($expenseClaim->fresh()->load('lines')));
    }

    public function reject(Request $request, ExpenseClaim $expenseClaim, WorkflowEngine $engine)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $engine->apply('expense_claim', $expenseClaim, $request->user(), 'reject');
        $expenseClaim->update(['rejected_at' => now(), 'rejection_reason' => $data['reason'] ?? 'No reason given.']);

        return $this->success(new ExpenseClaimResource($expenseClaim->fresh()->load('lines')));
    }

    private function scopedQuery(Request $request, PermissionService $permissions)
    {
        $user = $request->user();
        $scope = $permissions->scopeFor($user, 'expense_claims');
        $employee = $user->employee;

        return match ($scope) {
            'all' => ExpenseClaim::query(),
            'self_subordinates' => ExpenseClaim::whereIn('employee_id', $this->subordinateIds($employee)),
            'self' => ExpenseClaim::where('employee_id', $employee?->id ?? 0),
            default => ExpenseClaim::whereRaw('1 = 0'),
        };
    }

    private function canAccessRecord(Request $request, ExpenseClaim $expenseClaim, PermissionService $permissions): bool
    {
        $user = $request->user();
        $scope = $permissions->scopeFor($user, 'expense_claims');
        $employee = $user->employee;

        return match ($scope) {
            'all' => true,
            'self_subordinates' => in_array($expenseClaim->employee_id, $this->subordinateIds($employee), true),
            'self' => $employee && $expenseClaim->employee_id === $employee->id,
            default => false,
        };
    }

    private function authorizeRecord(Request $request, ExpenseClaim $expenseClaim, PermissionService $permissions): void
    {
        abort_unless($this->canAccessRecord($request, $expenseClaim, $permissions), 403, 'You do not have access to this expense claim.');
    }

    private function subordinateIds(?Employee $employee): array
    {
        if (! $employee) {
            return [];
        }

        return [$employee->id, ...$employee->subordinates()->pluck('id')->all()];
    }
}
