<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CaseResponse;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\Interview;
use App\Models\JobTitle;
use App\Models\SignatureEvent;
use App\Models\TrainingRecord;
use App\Models\Vacancy;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Security fix: every one of these collections defaulted to MediaLibrary's
 * `public` disk (no `useDisk('local')` call), which — via the standard
 * `storage:link` symlink — put Employee documents, Disciplinary Case files,
 * Candidate CVs, Expense receipts, Interview attachments, Signature
 * evidence, Training certificates, and Vacancy/JobTitle attachments at a
 * predictable, unauthenticated, world-readable URL, confirmed exploitable
 * with a plain unauthenticated curl request. Every affected model now uses
 * the private disk (see each model's registerMediaCollections()); this
 * controller is their one shared, authenticated replacement for the direct
 * `$media->getUrl()` links those views used to render — one gate per
 * domain, reusing that domain's own existing visibility rule (the same
 * data-group scope the corresponding screen/service already enforces)
 * rather than a new, parallel permission model.
 */
class PrivateMediaController extends Controller
{
    public function __invoke(Request $request, Media $media, PermissionService $permissions)
    {
        abort_unless($this->canAccess($request->user(), $media, $permissions), 403, 'You do not have access to this file.');

        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type]);
    }

    private function canAccess($user, Media $media, PermissionService $permissions): bool
    {
        $model = $media->model;

        return match (true) {
            $model instanceof Employee => $this->canAccessEmployee($user, $model, $permissions),
            $model instanceof DisciplinaryCase => $this->canAccessDisciplinaryCase($user, $model, $permissions),
            $model instanceof CaseResponse => $this->canAccessDisciplinaryCase($user, $model->case, $permissions),
            $model instanceof ExpenseClaim => $this->canAccessExpenseClaim($user, $model, $permissions),
            $model instanceof TrainingRecord => $this->canAccessEmployee($user, $model->employee, $permissions),
            $model instanceof Candidate, $model instanceof Interview, $model instanceof Vacancy => (bool) $user->canView('recruitment'),
            $model instanceof JobTitle => (bool) $user->canView('admin.master-data'),
            $model instanceof SignatureEvent => (bool) $user->canView('admin.signatures'),
            default => (bool) $user->isAdmin(),
        };
    }

    /** Spec B2's employee-record visibility scope — the same one the Employee show screen and its own Attachments tab already enforce. */
    private function canAccessEmployee($user, ?Employee $employee, PermissionService $permissions): bool
    {
        if (! $employee) {
            return false;
        }

        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'employee_personal_details');

        return match ($scope) {
            'all' => true,
            'self_subordinates' => $me && ($employee->id === $me->id || $employee->supervisor_id === $me->id),
            'self' => $me && $employee->id === $me->id,
            default => false,
        };
    }

    /** Mirrors ⚡discipline-manager.blade.php's own with() visibility query exactly (self, or raiser, or the employee's supervisor, under self_subordinates scope). */
    private function canAccessDisciplinaryCase($user, ?DisciplinaryCase $case, PermissionService $permissions): bool
    {
        if (! $case) {
            return false;
        }

        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'disciplinary_case');

        return match ($scope) {
            'all' => true,
            'self_subordinates' => $me && ($case->employee_id === $me->id || $case->raised_by === $me->id || $case->employee?->supervisor_id === $me->id),
            'self' => $me && $case->employee_id === $me->id,
            default => false,
        };
    }

    private function canAccessExpenseClaim($user, ExpenseClaim $claim, PermissionService $permissions): bool
    {
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'expense_claims');

        return match ($scope) {
            'all' => true,
            'self_subordinates' => $me && ($claim->employee_id === $me->id || $me->subordinates()->where('id', $claim->employee_id)->exists()),
            'self' => $me && $claim->employee_id === $me->id,
            default => false,
        };
    }
}
