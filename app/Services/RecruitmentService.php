<?php

namespace App\Services;

use App\Models\CandidateApplication;
use App\Models\CandidateHistory;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\Requisition;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Spec D1's business rules that sit above the generic WorkflowEngine:
 * approving a Requisition auto-creates its Vacancy, every pipeline
 * transition writes a CandidateHistory row, interview scheduling enforces
 * the two-round cap, and hiring auto-creates the Employee record (which in
 * turn triggers Employee ID assignment per Section B2).
 */
class RecruitmentService
{
    public function __construct(private WorkflowEngine $workflow) {}

    public function decideRequisition(Requisition $requisition, User $user, string $action, ?string $comment): void
    {
        $this->workflow->apply('requisition', $requisition, $user, $action);

        $requisition->update(['decision_comment' => $comment, 'decided_by' => $user->id, 'decided_at' => now()]);

        if ($requisition->status === 'approved') {
            Vacancy::create([
                'requisition_id' => $requisition->id,
                'title' => $requisition->title,
                'description' => $requisition->justification,
                'position_count' => $requisition->position_count,
                'hiring_manager_id' => $requisition->hiring_manager_id,
                'job_title_id' => $requisition->job_title_id,
            ]);
        }
    }

    /** 'schedule_interview' must go through scheduleInterview() instead — it needs interview details and enforces the two-round cap, both of which this generic dispatch would otherwise bypass. */
    public function applyPipelineAction(CandidateApplication $application, User $user, string $action, ?string $note = null): void
    {
        abort_if($action === 'schedule_interview', 500, 'Use scheduleInterview() to schedule an interview.');

        $this->workflow->apply('candidate_pipeline', $application, $user, $action);

        CandidateHistory::create([
            'candidate_application_id' => $application->id,
            'performed_by' => $user->id,
            'action' => $action,
            'note' => $note,
        ]);

        if ($application->status === 'hired') {
            $this->hire($application);
        }
    }

    public function scheduleInterview(CandidateApplication $application, User $user, array $data): Interview
    {
        if ($application->interviews()->count() >= Interview::MAX_ROUNDS_PER_APPLICATION) {
            throw ValidationException::withMessages(['interview' => 'This candidate has already had the maximum of '.Interview::MAX_ROUNDS_PER_APPLICATION.' interview rounds for this vacancy.']);
        }

        return DB::transaction(function () use ($application, $user, $data) {
            if (! in_array($application->status, ['shortlisted', 'interview_passed'], true)) {
                throw ValidationException::withMessages(['interview' => 'This candidate cannot have an interview scheduled from its current stage.']);
            }

            $this->workflow->apply('candidate_pipeline', $application, $user, 'schedule_interview');

            $interview = Interview::create([
                'candidate_application_id' => $application->id,
                'name' => $data['name'],
                'interview_date' => $data['interviewDate'],
                'interview_time' => $data['interviewTime'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $interview->interviewers()->sync($data['interviewerIds'] ?? []);

            CandidateHistory::create([
                'candidate_application_id' => $application->id,
                'interview_id' => $interview->id,
                'performed_by' => $user->id,
                'action' => 'schedule_interview',
                'note' => "Interview \"{$interview->name}\" scheduled for {$interview->interview_date->format('j M Y')}.",
            ]);

            return $interview;
        });
    }

    /** Spec D1: "Hiring automatically creates the corresponding Employee record from the candidate's details and the vacancy's job title." */
    private function hire(CandidateApplication $application): Employee
    {
        $candidate = $application->candidate;
        $vacancy = $application->vacancy;
        $generator = app(EmployeeIdGenerator::class);
        $hireDate = now();

        $employee = Employee::create([
            'employee_id' => $generator->generate($hireDate),
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'initials' => Str::upper(Str::substr($candidate->first_name, 0, 1).Str::substr($candidate->last_name, 0, 1)),
            'job_title_id' => $vacancy->job_title_id,
            'hire_date' => $hireDate,
            'personal_email' => $candidate->email,
            'phone_mobile' => $candidate->phone,
            // The hiring manager becomes the new hire's initial supervisor —
            // not stated verbatim in spec D1, but the only reporting-line
            // fact the pipeline actually knows at hire time.
            'supervisor_id' => $vacancy->hiring_manager_id,
        ]);

        return $employee;
    }
}
