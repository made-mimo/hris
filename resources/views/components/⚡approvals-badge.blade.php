<?php

use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\Timesheet;
use App\Services\WorkflowEngine;
use Livewire\Component;

/**
 * Spec F2: "pending-approval counts now update live...rather than only on
 * page load." No Reverb/WebSocket server runs on this dev box (see the
 * notification bell's own doc comment for the same honest trade-off), so
 * `wire:poll` stands in for a real push. Counts come from
 * WorkflowEngine::pendingFor() — the same reporting-line-scoped source the
 * Approvals board itself queries — replacing an earlier org-wide, unscoped
 * `pending_hr` count that over/under-counted for anyone who wasn't HR.
 */
new class extends Component
{
    public function with(WorkflowEngine $engine): array
    {
        $user = auth()->user();

        if (! $user->canView('approvals')) {
            return ['count' => 0];
        }

        $count = $engine->pendingFor($user, 'leave_request', LeaveRequest::class, ['pending_manager', 'pending_hr'])->count()
            + $engine->pendingFor($user, 'expense_claim', ExpenseClaim::class, ['pending_manager', 'pending_hr', 'pending_second_approval'])->count();

        return ['count' => $count];
    }
};
?>

<span wire:poll.15s>
    @if($count > 0)
        <span class="pill" style="background:var(--color-primary);color:#fff;min-width:20px;justify-content:center;">{{ $count }}</span>
    @endif
</span>
