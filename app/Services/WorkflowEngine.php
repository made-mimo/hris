<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The generic workflow-state-machine engine (spec Section 3.2): "a single
 * reusable engine of the shape (workflow, state, role, action) → resulting
 * state should back every approval process... 'What can this user do right
 * now' is answered by resolving the record's current state and the caller's
 * applicable role(s) against this table."
 *
 * A "role" here includes the two situational actor tags every approval
 * process in the spec actually needs alongside the RBAC role catalog: the
 * record's own owner, and that specific owner's supervisor — resolved live
 * per record, the same pattern PermissionService uses for the Supervisor
 * role, not stored anywhere.
 */
class WorkflowEngine
{
    public function __construct(private NotificationService $notifications) {}

    /** @return array<int, string> */
    public function actorTags(User $user, Model $record): array
    {
        $tags = [];

        if ($user->role) {
            $tags[] = $user->role->slug;
        }

        $employee = $user->employee;
        if ($employee) {
            if ((int) $record->employee_id === $employee->id) {
                $tags[] = 'owner';
            }
            if ($record->employee && (int) $record->employee->supervisor_id === $employee->id) {
                $tags[] = 'supervisor';
            }
        }

        return array_values(array_unique($tags));
    }

    /** @return Collection<int, WorkflowTransition> every action this user may legally take on this record right now */
    public function availableTransitions(string $workflow, string $state, User $user, Model $record): Collection
    {
        $tags = $this->actorTags($user, $record);

        if (empty($tags)) {
            return collect();
        }

        return WorkflowTransition::where('workflow', $workflow)
            ->where('from_state', $state)
            ->whereIn('actor', $tags)
            ->get();
    }

    public function can(string $workflow, Model $record, User $user, string $action, string $stateColumn = 'status'): bool
    {
        return $this->availableTransitions($workflow, $record->{$stateColumn}, $user, $record)
            ->contains('action', $action);
    }

    /**
     * Perform a transition, validated against the matrix — the only path
     * that may change a workflow-governed record's state. Aborts 403 if the
     * matrix doesn't grant this user this action from the record's current
     * state, so "the caller's currently valid actions" (spec E1) is always
     * the same answer whether asked before rendering a button or before
     * acting on a click.
     */
    public function apply(string $workflow, Model $record, User $user, string $action, string $stateColumn = 'status'): void
    {
        $transition = $this->availableTransitions($workflow, $record->{$stateColumn}, $user, $record)
            ->firstWhere('action', $action);

        abort_unless($transition, 403, "That action isn't available on this record right now.");

        $record->{$stateColumn} = $transition->to_state;
        $record->save();

        $this->notifyNextActors($workflow, $record, $transition);
    }

    /**
     * Spec's "[+ roles to notify]" fan-out, now that the Notification Center
     * (A7) exists: notify whoever can legally act next from the record's new
     * state — the same actor-tag vocabulary (owner/supervisor/RBAC role)
     * `availableTransitions()` already resolves, just aimed at "who holds
     * this tag" instead of "does this user hold it." No per-record detail
     * page exists in this prototype, so the deep link goes to the relevant
     * list screen rather than a specific record — spec's own fallback for a
     * link that can't point at an exact record.
     */
    private function notifyNextActors(string $workflow, Model $record, WorkflowTransition $justApplied): void
    {
        $nextTags = WorkflowTransition::where('workflow', $workflow)
            ->where('from_state', $justApplied->to_state)
            ->pluck('actor')
            ->unique();

        $recipients = collect();

        foreach ($nextTags as $tag) {
            $recipients = $recipients->merge(match ($tag) {
                'owner' => $record->employee?->user ? [$record->employee->user] : [],
                'supervisor' => $record->employee?->supervisor?->user ? [$record->employee->supervisor->user] : [],
                default => Role::where('slug', $tag)->first()?->users ?? [],
            });
        }

        $title = ucfirst(str_replace('_', ' ', $workflow)).' update';
        $body = "\"{$justApplied->label}\" — now {$justApplied->to_state}.";
        $deepLink = $workflow === 'leave' ? '/leave/apply' : ($workflow === 'expense_claim' ? '/claims/create' : '/approvals');

        foreach ($recipients->unique('id') as $recipient) {
            $this->notifications->notify($recipient, $workflow.'.transition', $title, $body, $deepLink, ucfirst($workflow));
        }
    }

    /**
     * Every record of this workflow the given user currently has at least
     * one legal action on — what an approvals queue screen actually needs.
     * Filters in PHP rather than SQL at this scale (a few dozen open
     * records); the natural next step if that stops being true is pushing
     * the actor-tag resolution into the query instead.
     *
     * @param  class-string<Model>  $modelClass
     * @return Collection<int, Model>
     */
    public function pendingFor(User $user, string $workflow, string $modelClass, array $openStates, string $stateColumn = 'status'): Collection
    {
        return $modelClass::whereIn($stateColumn, $openStates)->get()
            ->filter(fn (Model $record) => $this->availableTransitions($workflow, $record->{$stateColumn}, $user, $record)->isNotEmpty())
            ->values();
    }

    /** The label of the (first) action this user may take right now, for a button. */
    public function primaryLabel(string $workflow, Model $record, User $user, string $stateColumn = 'status'): ?string
    {
        return $this->availableTransitions($workflow, $record->{$stateColumn}, $user, $record)
            ->reject(fn (WorkflowTransition $t) => $t->action === 'reject')
            ->first()?->label;
    }
}
