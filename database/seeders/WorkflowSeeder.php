<?php

namespace Database\Seeders;

use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;

/**
 * The Role Permission Matrix's counterpart for *process*, not access: the
 * generic workflow-state-machine engine's own matrix (spec Section 3.2).
 * Two workflows, matching what this prototype's Apply Leave / Approvals /
 * Expense Claim screens actually drive — "Two-stage approval: Line Manager,
 * then HR," per the adopted UI artifact's own copy (see PLAN.md's note on
 * this vs. spec C1's "single-level, whichever acts first" prose — the
 * artifact's two-stage sequence is what's built and tested, flagged there
 * for SI to reconcile).
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ---- leave_request ----
            ['leave_request', 'pending_manager', 'supervisor', 'approve', 'Approve', 'pending_hr'],
            ['leave_request', 'pending_manager', 'supervisor', 'reject', 'Reject', 'rejected'],
            ['leave_request', 'pending_hr', 'hr_admin', 'approve', 'Give final approval', 'approved'],
            ['leave_request', 'pending_hr', 'hr_admin', 'reject', 'Reject', 'rejected'],
            ['leave_request', 'pending_hr', 'hr_officer', 'approve', 'Give final approval', 'approved'],
            ['leave_request', 'pending_hr', 'hr_officer', 'reject', 'Reject', 'rejected'],
            ['leave_request', 'pending_manager', 'owner', 'cancel', 'Cancel request', 'cancelled'],
            ['leave_request', 'pending_hr', 'owner', 'cancel', 'Cancel request', 'cancelled'],
            // Spec C1: cancelling an already-approved (scheduled, not yet
            // taken) request, and the leave-type-deletion "restricted, cancel
            // only" state (App\Models\LeaveType-deletion moves open requests
            // here directly — never reached via a normal transition).
            ['leave_request', 'approved', 'owner', 'cancel', 'Cancel request', 'cancelled'],
            ['leave_request', 'approved', 'hr_admin', 'cancel', 'Cancel (admin)', 'cancelled'],
            ['leave_request', 'restricted', 'owner', 'cancel', 'Cancel request', 'cancelled'],
            ['leave_request', 'restricted', 'hr_admin', 'cancel', 'Cancel (admin)', 'cancelled'],

            // ---- expense_claim ----
            ['expense_claim', 'pending_manager', 'supervisor', 'approve', 'Approve', 'pending_hr'],
            ['expense_claim', 'pending_manager', 'supervisor', 'reject', 'Reject', 'rejected'],
            ['expense_claim', 'pending_hr', 'hr_admin', 'approve', 'Approve and route to Finance', 'approved'],
            ['expense_claim', 'pending_hr', 'hr_admin', 'reject', 'Reject', 'rejected'],
            ['expense_claim', 'pending_hr', 'hr_officer', 'approve', 'Approve and route to Finance', 'approved'],
            ['expense_claim', 'pending_hr', 'hr_officer', 'reject', 'Reject', 'rejected'],
            // Payment tracking (spec E1): not yet exposed in any screen this
            // session — seeded so the matrix is complete and ready the
            // moment a "mark paid" action is built, per the engine's whole
            // point ("a policy change is a row edit, not new code").
            ['expense_claim', 'approved', 'hr_admin', 'mark_paid', 'Mark as paid', 'paid'],

            // ---- timesheet (spec C2) — single-stage (supervisor OR admin,
            // whichever acts), unlike Leave's two-stage sequence, and
            // Rejected supports resubmission rather than being terminal.
            // "admin" here is the system Admin role specifically (spec's own
            // "supervisors/admins"), not HR Admin — Time & Project Tracking
            // isn't an HR-specific approval chain the way Leave/Claims are.
            ['timesheet', 'not_submitted', 'owner', 'submit', 'Submit', 'submitted'],
            ['timesheet', 'submitted', 'supervisor', 'approve', 'Approve', 'approved'],
            ['timesheet', 'submitted', 'supervisor', 'reject', 'Reject', 'rejected'],
            ['timesheet', 'submitted', 'admin', 'approve', 'Approve', 'approved'],
            ['timesheet', 'submitted', 'admin', 'reject', 'Reject', 'rejected'],
            ['timesheet', 'rejected', 'owner', 'resubmit', 'Resubmit', 'submitted'],
            ['timesheet', 'rejected', 'supervisor', 'resubmit', 'Resubmit', 'submitted'],
            ['timesheet', 'rejected', 'admin', 'resubmit', 'Resubmit', 'submitted'],
            // Spec: "only an administrator may reset an approved timesheet
            // back to submitted (undo an approval)."
            ['timesheet', 'approved', 'admin', 'reset', 'Undo approval', 'submitted'],

            // ---- requisition (spec D1) — "a one-time, one-way decision."
            // HR Admin and HR Officer both decide, matching the same pairing
            // used for Leave/Expense Claim's HR-decision step.
            ['requisition', 'requested', 'hr_admin', 'approve', 'Approve (creates vacancy)', 'approved'],
            ['requisition', 'requested', 'hr_admin', 'reject', 'Reject', 'rejected'],
            ['requisition', 'requested', 'hr_officer', 'approve', 'Approve (creates vacancy)', 'approved'],
            ['requisition', 'requested', 'hr_officer', 'reject', 'Reject', 'rejected'],

            // ---- candidate_pipeline (spec D1) — "Application Initiated →
            // Shortlisted → Interview Scheduled → Interview Passed → Job
            // Offered → Hired," with Interview Failed/Offer Declined/Rejected
            // as reachable terminal branches. 'hiring_manager' is the
            // vacancy's own hiring manager (workflowActorTags, not
            // owner/supervisor); hr_admin/hr_officer can act on any pipeline
            // for HR oversight.
            ['candidate_pipeline', 'application_initiated', 'hiring_manager', 'shortlist', 'Shortlist', 'shortlisted'],
            ['candidate_pipeline', 'application_initiated', 'hr_admin', 'shortlist', 'Shortlist', 'shortlisted'],
            ['candidate_pipeline', 'application_initiated', 'hr_officer', 'shortlist', 'Shortlist', 'shortlisted'],
            ['candidate_pipeline', 'application_initiated', 'hiring_manager', 'reject', 'Reject', 'rejected'],
            ['candidate_pipeline', 'application_initiated', 'hr_admin', 'reject', 'Reject', 'rejected'],
            ['candidate_pipeline', 'application_initiated', 'hr_officer', 'reject', 'Reject', 'rejected'],

            ['candidate_pipeline', 'shortlisted', 'hiring_manager', 'schedule_interview', 'Schedule interview', 'interview_scheduled'],
            ['candidate_pipeline', 'shortlisted', 'hr_admin', 'schedule_interview', 'Schedule interview', 'interview_scheduled'],
            ['candidate_pipeline', 'shortlisted', 'hr_officer', 'schedule_interview', 'Schedule interview', 'interview_scheduled'],
            ['candidate_pipeline', 'shortlisted', 'hiring_manager', 'reject', 'Reject', 'rejected'],
            ['candidate_pipeline', 'shortlisted', 'hr_admin', 'reject', 'Reject', 'rejected'],
            ['candidate_pipeline', 'shortlisted', 'hr_officer', 'reject', 'Reject', 'rejected'],

            ['candidate_pipeline', 'interview_scheduled', 'hiring_manager', 'pass_interview', 'Mark interview passed', 'interview_passed'],
            ['candidate_pipeline', 'interview_scheduled', 'hr_admin', 'pass_interview', 'Mark interview passed', 'interview_passed'],
            ['candidate_pipeline', 'interview_scheduled', 'hr_officer', 'pass_interview', 'Mark interview passed', 'interview_passed'],
            ['candidate_pipeline', 'interview_scheduled', 'hiring_manager', 'fail_interview', 'Mark interview failed', 'interview_failed'],
            ['candidate_pipeline', 'interview_scheduled', 'hr_admin', 'fail_interview', 'Mark interview failed', 'interview_failed'],
            ['candidate_pipeline', 'interview_scheduled', 'hr_officer', 'fail_interview', 'Mark interview failed', 'interview_failed'],

            // A second interview round: back to scheduling without losing the
            // "interview_passed" semantics — spec caps at two rounds total
            // (Interview::MAX_ROUNDS_PER_APPLICATION); the service layer
            // enforces the count, not the state machine.
            ['candidate_pipeline', 'interview_passed', 'hiring_manager', 'schedule_interview', 'Schedule another interview', 'interview_scheduled'],
            ['candidate_pipeline', 'interview_passed', 'hr_admin', 'schedule_interview', 'Schedule another interview', 'interview_scheduled'],
            ['candidate_pipeline', 'interview_passed', 'hr_officer', 'schedule_interview', 'Schedule another interview', 'interview_scheduled'],
            ['candidate_pipeline', 'interview_passed', 'hiring_manager', 'make_offer', 'Extend offer', 'job_offered'],
            ['candidate_pipeline', 'interview_passed', 'hr_admin', 'make_offer', 'Extend offer', 'job_offered'],
            ['candidate_pipeline', 'interview_passed', 'hr_officer', 'make_offer', 'Extend offer', 'job_offered'],
            ['candidate_pipeline', 'interview_passed', 'hiring_manager', 'reject', 'Reject', 'rejected'],
            ['candidate_pipeline', 'interview_passed', 'hr_admin', 'reject', 'Reject', 'rejected'],
            ['candidate_pipeline', 'interview_passed', 'hr_officer', 'reject', 'Reject', 'rejected'],

            ['candidate_pipeline', 'job_offered', 'hiring_manager', 'hire', 'Confirm hire', 'hired'],
            ['candidate_pipeline', 'job_offered', 'hr_admin', 'hire', 'Confirm hire', 'hired'],
            ['candidate_pipeline', 'job_offered', 'hr_officer', 'hire', 'Confirm hire', 'hired'],
            ['candidate_pipeline', 'job_offered', 'hiring_manager', 'decline_offer', 'Offer declined', 'offer_declined'],
            ['candidate_pipeline', 'job_offered', 'hr_admin', 'decline_offer', 'Offer declined', 'offer_declined'],
            ['candidate_pipeline', 'job_offered', 'hr_officer', 'decline_offer', 'Offer declined', 'offer_declined'],
        ];

        foreach ($rows as [$workflow, $from, $actor, $action, $label, $to]) {
            WorkflowTransition::updateOrCreate(
                ['workflow' => $workflow, 'from_state' => $from, 'actor' => $actor, 'action' => $action],
                ['label' => $label, 'to_state' => $to]
            );
        }
    }
}
