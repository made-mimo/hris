<?php

namespace App\Services;

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

        // Notification fan-out (spec's "[+ roles to notify]") hooks in here
        // once the Notification Center (A7) exists — not built yet, see PLAN.md.
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
