<?php

use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use App\Services\WorkflowEngine;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Home backlog item 16 — "My open requests" on the Home page had nowhere to
 * click through to; this is that destination. Kept off the Home page itself
 * (a full-page SFC) per the "inert page + child component" rule, same
 * reasoning as ⚡my-recent-requests-table.blade.php.
 */
new class extends Component
{
    use WithPagination;

    protected WorkflowEngine $engine;

    protected LeaveRequestService $leaveRequests;

    public ?int $expandedLeaveId = null;

    public ?int $expandedClaimId = null;

    public function boot(WorkflowEngine $engine, LeaveRequestService $leaveRequests): void
    {
        $this->engine = $engine;
        $this->leaveRequests = $leaveRequests;
    }

    public function toggleLeave(int $id): void
    {
        $this->expandedLeaveId = $this->expandedLeaveId === $id ? null : $id;
    }

    public function toggleClaim(int $id): void
    {
        $this->expandedClaimId = $this->expandedClaimId === $id ? null : $id;
    }

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
            'leaveRequests' => $me->leaveRequests()->with(['leaveType', 'managerApprovedBy', 'hrApprovedBy'])
                ->latest()->paginate(10, pageName: 'leavePage'),
            'claims' => $me->expenseClaims()->with(['claimEvent', 'managerApprovedBy', 'hrApprovedBy', 'secondApprovedBy'])
                ->latest()->paginate(10, pageName: 'claimsPage'),
        ];
    }
};
?>

<div class="flex flex-col gap-5">
    @if(session('status'))
        <div class="pill pill-success" style="padding:10px 14px;">{{ session('status') }}</div>
    @endif

    <section class="card">
        <div class="card-header"><h2>Leave requests</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Request</th><th>Dates</th><th>Stage</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($leaveRequests as $item)
                        <tr style="cursor:pointer;" wire:click="toggleLeave({{ $item->id }})">
                            <td>
                                <div style="font-weight:600;">{{ $item->leaveType->name }} leave</div>
                                <div class="text-muted font-mono" style="font-size:var(--fs-xs);">{{ $item->reference }}</div>
                            </td>
                            <td class="text-muted">{{ $item->start_date->format('j M') }} – {{ $item->end_date->format('j M') }} · <span class="font-mono">{{ $item->days }}</span> days</td>
                            <td class="text-muted">{{ $item->stageLabel() }}</td>
                            <td>
                                <span class="pill {{ match(true) {
                                    str_contains($item->status,'pending') => 'pill-warning',
                                    $item->status === 'approved' => 'pill-neutral',
                                    $item->status === 'rejected' => 'pill-danger',
                                    default => 'pill-neutral',
                                } }}">{{ ucfirst(str_replace('_',' ',$item->status)) }}</span>
                            </td>
                            <td class="text-muted" style="text-align:right;">{{ $expandedLeaveId === $item->id ? '▲' : '▼' }}</td>
                        </tr>
                        @if($expandedLeaveId === $item->id)
                            <tr>
                                <td colspan="5" style="background:var(--color-bg);">
                                    <div style="padding:12px 6px;display:flex;flex-direction:column;gap:6px;font-size:var(--fs-sm);" onclick="event.stopPropagation()">
                                        @if($item->reason)
                                            <div><span class="text-muted">Reason: </span>{{ $item->reason }}</div>
                                        @endif
                                        @if($item->relieverEmployee)
                                            <div><span class="text-muted">Reliever: </span>{{ $item->relieverEmployee->fullName() }}</div>
                                        @endif
                                        @if($item->manager_approved_by)
                                            <div><span class="text-muted">Approved by line manager: </span>{{ $item->managerApprovedBy?->name }} on {{ $item->manager_approved_at?->format('j M Y, H:i') }}</div>
                                        @endif
                                        @if($item->hr_approved_by)
                                            <div><span class="text-muted">Approved by HR: </span>{{ $item->hrApprovedBy?->name }} on {{ $item->hr_approved_at?->format('j M Y, H:i') }}</div>
                                        @endif
                                        @if($item->status === 'rejected')
                                            <div><span class="text-muted">Rejected{{ $item->rejected_at ? ' on '.$item->rejected_at->format('j M Y, H:i') : '' }}: </span>{{ $item->rejection_reason ?: 'No reason given.' }}</div>
                                        @endif
                                        @if($item->hr_comment)
                                            <div><span class="text-muted">HR comment: </span>{{ $item->hr_comment }}</div>
                                        @endif
                                        @if(in_array($item->status, ['pending_manager', 'pending_hr'], true))
                                            <div class="text-muted">Waiting on {{ $item->stageLabel() }}.</div>
                                        @endif
                                        @if(in_array($item->status, ['pending_manager', 'pending_hr', 'approved', 'restricted'], true))
                                            <button type="button" wire:click="cancelLeave({{ $item->id }})" wire:confirm="Cancel this leave request?" class="btn btn-outline btn-sm" style="align-self:flex-start;margin-top:4px;">Cancel this request</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="text-muted">No leave requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $leaveRequests->links() }}</div>
    </section>

    <section class="card">
        <div class="card-header"><h2>Expense claims</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Claim</th><th>Event / Amount</th><th>Stage</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($claims as $claim)
                        <tr style="cursor:pointer;" wire:click="toggleClaim({{ $claim->id }})">
                            <td>
                                <div style="font-weight:600;">Expense claim</div>
                                <div class="text-muted font-mono" style="font-size:var(--fs-xs);">{{ $claim->reference }}</div>
                            </td>
                            <td class="text-muted">{{ $claim->claimEvent->name }} · <span class="font-mono">{{ $claim->currency }} {{ number_format($claim->total(), 2) }}</span></td>
                            <td class="text-muted">{{ $claim->stageLabel() }}</td>
                            <td>
                                <span class="pill {{ match(true) {
                                    str_contains($claim->status,'pending') => 'pill-warning',
                                    $claim->status === 'paid' => 'pill-success',
                                    $claim->status === 'approved' => 'pill-neutral',
                                    $claim->status === 'rejected' => 'pill-danger',
                                    default => 'pill-neutral',
                                } }}">{{ ucfirst(str_replace('_',' ',$claim->status)) }}</span>
                            </td>
                            <td class="text-muted" style="text-align:right;">{{ $expandedClaimId === $claim->id ? '▲' : '▼' }}</td>
                        </tr>
                        @if($expandedClaimId === $claim->id)
                            <tr>
                                <td colspan="5" style="background:var(--color-bg);">
                                    <div style="padding:12px 6px;display:flex;flex-direction:column;gap:6px;font-size:var(--fs-sm);">
                                        @if($claim->manager_approved_by)
                                            <div><span class="text-muted">Approved by line manager: </span>{{ $claim->managerApprovedBy?->name }} on {{ $claim->manager_approved_at?->format('j M Y, H:i') }}</div>
                                        @endif
                                        @if($claim->hr_approved_by)
                                            <div><span class="text-muted">Approved by HR: </span>{{ $claim->hrApprovedBy?->name }} on {{ $claim->hr_approved_at?->format('j M Y, H:i') }}</div>
                                        @endif
                                        @if($claim->second_approved_by)
                                            <div><span class="text-muted">Second approval: </span>{{ $claim->secondApprovedBy?->name }} on {{ $claim->second_approved_at?->format('j M Y, H:i') }}</div>
                                        @endif
                                        @if($claim->status === 'rejected')
                                            <div><span class="text-muted">Rejected{{ $claim->rejected_at ? ' on '.$claim->rejected_at->format('j M Y, H:i') : '' }}: </span>{{ $claim->rejection_reason ?: 'No reason given.' }}</div>
                                        @endif
                                        @if($claim->status === 'paid')
                                            <div><span class="text-muted">Paid: </span>{{ $claim->paid_at?->format('j M Y, H:i') }}{{ $claim->payment_reference ? ' · ref '.$claim->payment_reference : '' }}</div>
                                        @endif
                                        @if(in_array($claim->status, ['pending_manager', 'pending_hr', 'pending_second_approval'], true))
                                            <div class="text-muted">Waiting on {{ $claim->stageLabel() }}.</div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="text-muted">No expense claims yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $claims->links() }}</div>
    </section>
</div>
