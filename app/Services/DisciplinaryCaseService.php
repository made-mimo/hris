<?php

namespace App\Services;

use App\Models\CaseResponse;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Spec D3's business rules above the generic WorkflowEngine: strict
 * ownership scoping on who may raise/edit a case, HR-only progression past
 * the initial response, and e-signature-backed acknowledgement for Written
 * Warning and above.
 */
class DisciplinaryCaseService
{
    public function __construct(private WorkflowEngine $workflow, private NotificationService $notifications) {}

    /** Spec D3: "a supervisor may raise a case only against their own reporting-line subordinates." HR Admin/Admin may raise against anyone. */
    public function raise(Employee $subject, User $raisedByUser, array $data): DisciplinaryCase
    {
        $raiser = $raisedByUser->employee;
        $isHr = $raisedByUser->isAdmin() || $raisedByUser->isHr();

        if (! $isHr && (! $raiser || (int) $subject->supervisor_id !== $raiser->id)) {
            throw ValidationException::withMessages(['employee' => 'You may only raise a case against your own reporting-line subordinates.']);
        }

        $case = DisciplinaryCase::create([
            'employee_id' => $subject->id,
            'raised_by' => $raiser?->id ?? $subject->supervisor_id,
            'case_type' => $data['caseType'],
            'severity' => $data['severity'],
            'description' => $data['description'],
            'incident_date' => $data['incidentDate'],
            'status' => 'open',
        ]);

        if ($subject->user) {
            $this->notifications->notify($subject->user, 'discipline.raised', 'A query has been raised about you', $data['description'], '/discipline', 'Discipline');
        }
        $this->notifyHr($case, 'discipline.raised', "A disciplinary query was raised against {$subject->fullName()}.");

        return $case;
    }

    /** Spec D3: "may only edit it while still open and self-raised." */
    public function canEdit(DisciplinaryCase $case, User $user): bool
    {
        return $case->status === 'open' && $user->employee && $case->raised_by === $user->employee->id;
    }

    public function respond(DisciplinaryCase $case, User $user, string $body): CaseResponse
    {
        $this->workflow->apply('disciplinary_case', $case, $user, 'respond');

        $response = CaseResponse::create([
            'disciplinary_case_id' => $case->id,
            'responded_by' => $user->id,
            'type' => 'response',
            'body' => $body,
        ]);

        if ($case->raisedBy->user) {
            $this->notifications->notify($case->raisedBy->user, 'discipline.responded', 'A response was submitted to your query', $body, '/discipline', 'Discipline');
        }
        $this->notifyHr($case, 'discipline.responded', "{$case->employee->fullName()} responded to their disciplinary query.");

        return $response;
    }

    /** Spec D3: "resolving a case now requires selecting a formal outcome...rather than only a free-text resolution note." */
    public function resolve(DisciplinaryCase $case, User $user, string $outcome, ?string $resolutionNote): void
    {
        abort_unless(in_array($outcome, DisciplinaryCase::OUTCOMES, true), 422, 'Invalid outcome.');

        $this->workflow->apply('disciplinary_case', $case, $user, 'resolve');

        $case->update(['outcome' => $outcome, 'resolution_note' => $resolutionNote]);

        if ($case->employee->user) {
            $this->notifications->notify($case->employee->user, 'discipline.resolved', 'Your disciplinary case has been resolved', $case->outcomeLabel(), '/discipline', 'Discipline');
        }
        if ($case->raisedBy->user) {
            $this->notifications->notify($case->raisedBy->user, 'discipline.resolved', 'A case you raised has been resolved', $case->outcomeLabel(), '/discipline', 'Discipline');
        }
    }

    public function followUp(DisciplinaryCase $case, User $user, string $question): CaseResponse
    {
        $this->workflow->apply('disciplinary_case', $case, $user, 'follow_up');

        $response = CaseResponse::create([
            'disciplinary_case_id' => $case->id,
            'responded_by' => $user->id,
            'type' => 'follow_up',
            'body' => $question,
        ]);

        if ($case->employee->user) {
            $this->notifications->notify($case->employee->user, 'discipline.follow_up', 'A follow-up question has been raised on your case', $question, '/discipline', 'Discipline');
        }
        if ($case->raisedBy->user) {
            $this->notifications->notify($case->raisedBy->user, 'discipline.follow_up', 'A follow-up question was raised on a case you raised', $question, '/discipline', 'Discipline');
        }

        return $response;
    }

    /** Spec D3: e-signature-backed acknowledgement for Written Warning and above — captured from the employee, not HR. */
    public function acknowledgeOutcome(DisciplinaryCase $case, User $user, SignatureService $signatures): void
    {
        abort_unless($case->outcomeRequiresSignature(), 422, 'This outcome does not require acknowledgement.');
        abort_unless($user->employee && $user->employee->id === $case->employee_id, 403, 'Only the employee this case concerns may acknowledge it.');

        $content = "Disciplinary case #{$case->id} outcome acknowledgement: {$case->outcomeLabel()}.";

        abort_if($signatures->hasValidSignature($case, $user, 'disciplinary_outcome_acknowledgement', $content), 422, 'Already acknowledged.');

        $signatures->sign(
            signable: $case,
            signer: $user,
            purpose: 'disciplinary_outcome_acknowledgement',
            content: $content,
            method: 'click_to_sign',
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }

    private function notifyHr(DisciplinaryCase $case, string $type, string $body): void
    {
        Role::where('slug', 'hr_admin')->first()?->users->each(
            fn (User $u) => $this->notifications->notify($u, $type, 'Discipline case update', $body, '/discipline', 'Discipline')
        );
    }
}
