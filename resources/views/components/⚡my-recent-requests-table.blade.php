<?php

use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use App\Services\WorkflowEngine;
use Livewire\Component;

/**
 * Extracted from the Home page (spec's adopted artifact design — kept
 * pixel-identical, same theme.css classes) purely to give the owner-cancel
 * action a Livewire component to live in, per the "inert page + child
 * component" rule (PLAN.md 4.3): the Home page is a full-page SFC, so a
 * wire:click directly on it hits the full-document-morph defect already
 * found and fixed three times this session on other screens.
 */
new class extends Component
{
    protected WorkflowEngine $engine;

    protected LeaveRequestService $leaveRequests;

    public function boot(WorkflowEngine $engine, LeaveRequestService $leaveRequests): void
    {
        $this->engine = $engine;
        $this->leaveRequests = $leaveRequests;
    }

    /** Owner-initiated cancel — spec C1: one of the four leave status changes ("apply/approve/reject/cancel/assign") every path in this module must support. */
    public function cancelLeave(int $id): void
    {
        $request = LeaveRequest::where('employee_id', auth()->user()->employee->id)->findOrFail($id);

        $this->engine->apply('leave_request', $request, auth()->user(), 'cancel');
        $this->leaveRequests->syncAfterTransition($request);

        session()->flash('status', 'Leave request cancelled.');
    }

    public function with(): array
    {
        $me = auth()->user()->employee;

        return [
            'items' => $me->leaveRequests()->with('leaveType')->latest()->take(3)->get()
                ->concat($me->expenseClaims()->with('claimEvent')->latest()->take(3)->get())
                ->sortByDesc('created_at')->take(4),
        ];
    }
};
?>

<section class="card col-span-2">
    <div class="card-header">
        <h2>My recent requests</h2>
    </div>
    @if(session('status'))
        <div class="pill pill-success" style="margin:0 0 12px;padding:8px 12px;">{{ session('status') }}</div>
    @endif
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Request</th><th>Details</th><th>Stage</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($items as $item)
                    @php $isLeave = $item instanceof \App\Models\LeaveRequest; @endphp
                    <tr>
                        <td>
                            <div style="font-weight:600;">{{ $isLeave ? $item->leaveType->name.' leave' : 'Expense claim' }}</div>
                            <div class="text-muted font-mono" style="font-size:12px;">{{ $item->reference }}</div>
                        </td>
                        <td class="text-muted">
                            @if($isLeave)
                                {{ $item->start_date->format('j M') }} – {{ $item->end_date->format('j M') }} · <span class="font-mono">{{ $item->days }}</span> days
                            @else
                                {{ $item->claimEvent->name }} · <span class="font-mono">₦{{ number_format($item->total()) }}</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $item->stageLabel() }}</td>
                        <td>
                            @php $status = $item->status; @endphp
                            <span class="pill {{ match(true) {
                                str_contains($status,'pending') => 'pill-warning',
                                $status === 'approved' => 'pill-neutral',
                                $status === 'paid' => 'pill-success',
                                $status === 'rejected' => 'pill-danger',
                                default => 'pill-neutral',
                            } }}">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
                            @if($isLeave && in_array($status, ['pending_manager', 'pending_hr', 'approved', 'restricted'], true))
                                <button type="button" wire:click="cancelLeave({{ $item->id }})" wire:confirm="Cancel this leave request?" style="margin-left:8px;font-size:12px;font-weight:600;color:var(--color-danger);background:none;border:none;cursor:pointer;padding:0;">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No requests yet — try Apply for leave or Submit a claim.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
