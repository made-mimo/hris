<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskCategoryHandler;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;

/**
 * Spec F4: "a ticket and its comment thread are visible only to the raiser
 * and to HR/Admin — deliberately not to the raiser's manager chain."
 * Confidential (Grievance/Whistleblower) categories narrow that further to
 * a small, Admin-designated handler list instead of every HR Admin/Officer.
 *
 * No WorkflowEngine here, deliberately: every status transition is
 * performed by the same actor class (HR/Admin), with no multi-stage
 * actor handoff or reporting-line resolution to justify the generic
 * engine — the same judgment already applied to Asset/Vehicle status and
 * Company Document activation elsewhere in this app.
 */
class TicketService
{
    public function raise(Employee $employee, HelpdeskCategory $category, string $subject, string $description, bool $isAnonymous): Ticket
    {
        return Ticket::create([
            'subject' => $subject,
            'description' => $description,
            'helpdesk_category_id' => $category->id,
            'status' => 'open',
            'raised_by_id' => $employee->id,
            'is_anonymous' => $isAnonymous,
        ]);
    }

    public function addComment(Ticket $ticket, Employee $author, string $body): TicketComment
    {
        return TicketComment::create(['ticket_id' => $ticket->id, 'employee_id' => $author->id, 'body' => $body]);
    }

    public function isHandler(HelpdeskCategory $category, User $user): bool
    {
        $employee = $user->employee;

        return HelpdeskCategoryHandler::where('helpdesk_category_id', $category->id)
            ->where(function ($q) use ($user, $employee) {
                $q->where('role_id', $user->role_id);
                if ($employee) {
                    $q->orWhere('employee_id', $employee->id);
                }
            })
            ->exists();
    }

    public function canView(Ticket $ticket, User $user): bool
    {
        $employee = $user->employee;

        if ($employee && $ticket->raised_by_id === $employee->id) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($ticket->category->is_confidential) {
            return $this->isHandler($ticket->category, $user);
        }

        return $user->isHr();
    }

    public function canManageStatus(Ticket $ticket, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($ticket->category->is_confidential) {
            return $this->isHandler($ticket->category, $user);
        }

        return $user->isHr();
    }

    public function startProgress(Ticket $ticket): void
    {
        $ticket->update(['status' => 'in_progress']);
    }

    public function resolve(Ticket $ticket): void
    {
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    public function close(Ticket $ticket): void
    {
        $ticket->update(['status' => 'closed', 'resolved_at' => $ticket->resolved_at ?? now()]);
    }

    /** Spec F4: "resolved-date bookkeeping...cleared if reopened." */
    public function reopen(Ticket $ticket): void
    {
        $ticket->update(['status' => 'open', 'resolved_at' => null]);
    }

    public function assign(Ticket $ticket, Employee $assignee): void
    {
        $ticket->update(['assigned_to_id' => $assignee->id]);
    }

    /** Spec F4: "logged whenever an Admin/Owner specifically looks it up — a deliberate, access-restricted, fully-audited exception." */
    public function revealIdentity(Ticket $ticket, User $viewer): string
    {
        abort_unless($this->canView($ticket, $viewer), 403);
        abort_unless($ticket->is_anonymous, 422, 'This ticket was not submitted anonymously.');

        AuditLog::create([
            'auditable_type' => Ticket::class,
            'auditable_id' => $ticket->id,
            'action' => 'identity_revealed',
            'actor_id' => $viewer->id,
            'actor_label' => $viewer->name,
            'changes' => null,
        ]);

        return $ticket->raisedBy->fullName();
    }

    public function grantHandler(int $categoryId, ?int $roleId, ?int $employeeId): HelpdeskCategoryHandler
    {
        return HelpdeskCategoryHandler::firstOrCreate([
            'helpdesk_category_id' => $categoryId,
            'role_id' => $roleId,
            'employee_id' => $employeeId,
        ]);
    }
}
