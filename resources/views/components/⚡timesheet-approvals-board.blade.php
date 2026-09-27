<?php

use App\Models\Timesheet;
use App\Services\WorkflowEngine;
use Livewire\Component;

/** Spec C2: "Approval queues for supervisors/admins across their accessible employees; full action-log audit trail per timesheet." Reuses WorkflowEngine::pendingFor() the same way approvals-board does for leave/claims. */
new class extends Component
{
    public ?int $selectedId = null;

    public string $rejectionReason = '';

    protected WorkflowEngine $engine;

    public function boot(WorkflowEngine $engine): void
    {
        $this->engine = $engine;
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->rejectionReason = '';
    }

    protected function resolvedSelectedId(): ?int
    {
        return $this->selectedId ?? $this->pending()->first()?->id;
    }

    public function approve(): void
    {
        $timesheet = Timesheet::findOrFail($this->resolvedSelectedId());
        $this->engine->apply('timesheet', $timesheet, auth()->user(), 'approve');
        $timesheet->update(['approved_by' => auth()->id(), 'approved_at' => now()]);
        $timesheet->logAction(auth()->user(), 'approved');
        $this->selectedId = null;
    }

    public function reject(): void
    {
        $timesheet = Timesheet::findOrFail($this->resolvedSelectedId());
        $this->engine->apply('timesheet', $timesheet, auth()->user(), 'reject');
        $timesheet->update(['rejection_reason' => $this->rejectionReason ?: 'No reason given.']);
        $timesheet->logAction(auth()->user(), 'rejected', $this->rejectionReason ?: null);
        $this->selectedId = null;
    }

    /** Spec: "only an administrator may reset an approved timesheet back to submitted (undo an approval)." */
    public function resetApproval(int $id): void
    {
        $timesheet = Timesheet::findOrFail($id);
        $this->engine->apply('timesheet', $timesheet, auth()->user(), 'reset');
        $timesheet->logAction(auth()->user(), 'reset to submitted');
    }

    protected function pending()
    {
        return $this->engine->pendingFor(auth()->user(), 'timesheet', Timesheet::class, ['submitted'])
            ->load(['employee', 'lines.project', 'lines.activity'])
            ->sortBy('submitted_at');
    }

    public function with(): array
    {
        $pending = $this->pending();
        $selected = $pending->firstWhere('id', $this->resolvedSelectedId());

        // Admin-only "undo approval" list — separate from the main approval
        // queue since it's a correction action, not a normal pending item.
        $isAdmin = auth()->user()->isAdmin();
        $recentlyApproved = $isAdmin
            ? Timesheet::where('status', 'approved')->with('employee')->latest('approved_at')->take(10)->get()
            : collect();

        return [
            'pending' => $pending,
            'selected' => $selected,
            'recentlyApproved' => $recentlyApproved,
        ];
    }
};
?>

<div class="grid grid-3" style="align-items:start;">
    <section class="card col-span-2" style="padding:0;overflow:hidden;">
        <div class="card-header" style="padding:16px 18px;">
            <h2>Pending ({{ $pending->count() }})</h2>
        </div>
        <div class="table-wrap">
            @foreach($pending as $timesheet)
                <div wire:click="select({{ $timesheet->id }})" style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--color-border);cursor:pointer;{{ $selected?->id === $timesheet->id ? 'background:var(--color-bg);' : '' }}">
                    <div>
                        <div style="font-weight:600;">{{ $timesheet->employee->fullName() }}</div>
                        <div class="text-muted" style="font-size:12px;">{{ $timesheet->week_start_date->format('j M') }} – {{ $timesheet->week_end_date->format('j M Y') }} · {{ number_format($timesheet->totalHours(), 2) }}h</div>
                    </div>
                    <span class="pill pill-warning">Submitted</span>
                </div>
            @endforeach
            @if($pending->isEmpty())
                <div class="hint" style="padding:24px 18px;">Nothing waiting.</div>
            @endif
        </div>
    </section>

    <section class="card">
        @if($selected)
            <div class="card-header"><h2>{{ $selected->employee->fullName() }}</h2></div>
            <div class="text-muted" style="font-size:13px;margin-bottom:14px;">{{ $selected->week_start_date->format('j M') }} – {{ $selected->week_end_date->format('j M Y') }}</div>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
                @foreach($selected->lines as $line)
                    <div style="display:flex;justify-content:space-between;font-size:13px;">
                        <span>{{ $line->project->name }} · {{ $line->activity->name }}</span>
                        <span class="font-mono" style="font-weight:600;">{{ number_format($line->totalHours(), 2) }}h</span>
                    </div>
                @endforeach
            </div>
            <div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid var(--color-border);padding-top:10px;margin-bottom:16px;">
                <span>Total</span><span class="font-mono">{{ number_format($selected->totalHours(), 2) }}h</span>
            </div>

            <div class="field">
                <label for="rejectionReason">Rejection reason <span class="text-muted" style="font-weight:400;">(if rejecting)</span></label>
                <textarea id="rejectionReason" rows="2" wire:model="rejectionReason"></textarea>
            </div>

            <div style="display:flex;gap:10px;">
                <button wire:click="approve" class="btn btn-primary">Approve</button>
                <button wire:click="reject" class="btn btn-outline">Reject</button>
            </div>
        @else
            <div class="hint">Select a timesheet from the list.</div>
        @endif
    </section>

    @if($recentlyApproved->isNotEmpty())
        <section class="card col-span-3">
            <div class="card-header"><h2>Recently approved (Admin: undo)</h2></div>
            <div class="table-wrap">
                @foreach($recentlyApproved as $timesheet)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--color-border);">
                        <span style="font-size:13px;">{{ $timesheet->employee->fullName() }} — {{ $timesheet->week_start_date->format('j M') }} to {{ $timesheet->week_end_date->format('j M Y') }}</span>
                        <button wire:click="resetApproval({{ $timesheet->id }})" wire:confirm="Undo this approval and send it back to submitted?" class="text-xs font-semibold" style="color:var(--color-danger);background:none;border:none;cursor:pointer;">Undo approval</button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
