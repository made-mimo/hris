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
        ];

        foreach ($rows as [$workflow, $from, $actor, $action, $label, $to]) {
            WorkflowTransition::updateOrCreate(
                ['workflow' => $workflow, 'from_state' => $from, 'actor' => $actor, 'action' => $action],
                ['label' => $label, 'to_state' => $to]
            );
        }
    }
}
