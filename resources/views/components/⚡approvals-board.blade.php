<?php

use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Services\WorkflowEngine;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'all';
    public ?string $selectedKey = null;
    public string $comment = '';

    protected WorkflowEngine $engine;

    public function boot(WorkflowEngine $engine): void
    {
        $this->engine = $engine;
    }

    public function pickTab(string $tab): void
    {
        $this->tab = $tab;
        $this->selectedKey = null;
    }

    public function select(string $key): void
    {
        $this->selectedKey = $key;
        $this->comment = '';
    }

    /** The selected row's key, falling back to the first visible row — the
     *  same default the list/detail view applies, so an action taken without
     *  ever clicking a row (the pre-selected first item) resolves correctly. */
    protected function resolvedSelectedKey(): ?string
    {
        return $this->selectedKey ?? $this->items()
            ->when($this->tab !== 'all', fn ($c) => $c->where('kind', $this->tab))
            ->first()['key'] ?? null;
    }

    protected function findRecord(string $key): array
    {
        [$kind, $id] = explode('-', $key, 2);
        $model = $kind === 'leave' ? LeaveRequest::findOrFail($id) : ExpenseClaim::findOrFail($id);
        $workflow = $kind === 'leave' ? 'leave_request' : 'expense_claim';

        return [$model, $workflow];
    }

    public function approve(): void
    {
        [$record, $workflow] = $this->findRecord($this->resolvedSelectedKey());
        $wasPendingManager = $record->status === 'pending_manager';

        $this->engine->apply($workflow, $record, auth()->user(), 'approve');

        // Stamp the domain-specific audit columns for whichever stage just
        // cleared — the engine itself only knows about the generic `status`
        // column, per spec's separation of workflow *process* from a
        // module's own record-keeping.
        $record->update($wasPendingManager
            ? ['manager_approved_by' => auth()->id(), 'manager_approved_at' => now()]
            : ['hr_approved_by' => auth()->id(), 'hr_approved_at' => now()]);

        $this->selectedKey = null;
    }

    public function reject(): void
    {
        [$record, $workflow] = $this->findRecord($this->resolvedSelectedKey());

        $this->engine->apply($workflow, $record, auth()->user(), 'reject');
        $record->update(['rejected_at' => now(), 'rejection_reason' => $this->comment ?: 'No reason given.']);

        $this->selectedKey = null;
    }

    protected function items(): \Illuminate\Support\Collection
    {
        $user = auth()->user();

        $leave = $this->engine->pendingFor($user, 'leave_request', LeaveRequest::class, ['pending_manager', 'pending_hr'])
            ->load(['employee.supervisor', 'leaveType', 'relieverEmployee'])
            ->map(function (LeaveRequest $r) use ($user) {
                $stageLabel = $r->status === 'pending_manager' ? 'Line Manager approval' : 'HR final approval';

                return [
                    'key' => "leave-{$r->id}", 'kind' => 'leave', 'kindLabel' => 'Leave',
                    'who' => $r->employee->fullName(), 'initials' => $r->employee->initials, 'dept' => $r->employee->department,
                    'ref' => $r->reference, 'title' => "{$r->leaveType->name} leave · {$r->days} days",
                    'summary' => $r->start_date->format('j M').' – '.$r->end_date->format('j M').($r->relieverEmployee ? ' · reliever '.$r->relieverEmployee->fullName() : ''),
                    'stage' => $stageLabel, 'age' => ($r->status === 'pending_manager' ? $r->created_at : $r->manager_approved_at)?->diffForHumans(null, true) ?? '—',
                    'primary' => $this->engine->primaryLabel('leave_request', $r, $user) ?? 'Approve',
                    'facts' => [
                        ['k' => 'Dates', 'v' => $r->start_date->format('D j M Y').' – '.$r->end_date->format('D j M Y')],
                        ['k' => 'Duration', 'v' => "{$r->days} days · {$r->duration_type}"],
                        ['k' => 'Balance after', 'v' => number_format($r->employee->leaveBalance($r->leaveType)['available'] - $r->days, 1).' of '.number_format($r->employee->leaveBalance($r->leaveType)['entitled'], 1)],
                        ['k' => 'Reliever', 'v' => $r->relieverEmployee?->fullName() ?? '—'],
                    ],
                    'trail' => [
                        ['label' => 'Submitted by '.$r->employee->fullName(), 'state' => 'done'],
                        ['label' => 'Line Manager · '.($r->employee->supervisor?->fullName() ?? '—'), 'state' => $r->status === 'pending_manager' ? 'current' : 'done'],
                        ['label' => 'HR final approval', 'state' => $r->status === 'pending_hr' ? 'current' : 'todo'],
                    ],
                ];
            });

        $claims = $this->engine->pendingFor($user, 'expense_claim', ExpenseClaim::class, ['pending_manager', 'pending_hr'])
            ->load(['employee.supervisor', 'claimEvent', 'lines'])
            ->map(function (ExpenseClaim $c) use ($user) {
                $stageLabel = $c->status === 'pending_manager' ? 'Line Manager approval' : 'HR review';

                return [
                    'key' => "claim-{$c->id}", 'kind' => 'claim', 'kindLabel' => 'Claim',
                    'who' => $c->employee->fullName(), 'initials' => $c->employee->initials, 'dept' => $c->employee->department,
                    'ref' => $c->reference, 'title' => "{$c->claimEvent->name} · ₦".number_format($c->total()),
                    'summary' => '₦'.number_format($c->total()).' · '.$c->lines->count().' item'.($c->lines->count() === 1 ? '' : 's'),
                    'stage' => $stageLabel, 'age' => ($c->status === 'pending_manager' ? $c->submitted_at : $c->manager_approved_at)?->diffForHumans(null, true) ?? '—',
                    'primary' => $this->engine->primaryLabel('expense_claim', $c, $user) ?? 'Approve',
                    'facts' => [
                        ['k' => 'Claim event', 'v' => $c->claimEvent->name],
                        ['k' => 'Total', 'v' => '₦'.number_format($c->total(), 2)],
                        ['k' => 'Line items', 'v' => $c->lines->count().($c->lines->contains('flagged', true) ? ' · has a flagged line' : ' · no cap flags')],
                        ['k' => 'Next approver', 'v' => $c->status === 'pending_manager' ? 'HR & Admin next' : 'Finance (payment)'],
                    ],
                    'trail' => [
                        ['label' => 'Submitted by '.$c->employee->fullName(), 'state' => 'done'],
                        ['label' => 'Line Manager · '.($c->employee->supervisor?->fullName() ?? '—'), 'state' => $c->status === 'pending_manager' ? 'current' : 'done'],
                        ['label' => 'HR review', 'state' => $c->status === 'pending_hr' ? 'current' : 'todo'],
                    ],
                ];
            });

        return $leave->concat($claims)->sortBy('age');
    }

    public function with(): array
    {
        $all = $this->items();
        $tabs = [
            ['id' => 'all', 'label' => 'All', 'count' => $all->count()],
            ['id' => 'leave', 'label' => 'Leave', 'count' => $all->where('kind', 'leave')->count()],
            ['id' => 'claim', 'label' => 'Claims', 'count' => $all->where('kind', 'claim')->count()],
        ];

        $visible = $this->tab === 'all' ? $all : $all->where('kind', $this->tab);
        $selected = $visible->firstWhere('key', $this->selectedKey) ?? $visible->first();

        return ['tabs' => $tabs, 'visible' => $visible->values(), 'selected' => $selected];
    }
};
?>

<div>
    <div role="tablist" aria-label="Filter approvals" style="display:flex;gap:6px;border-bottom:1px solid var(--color-border);margin-bottom:16px;">
        @foreach($tabs as $t)
            <button type="button" role="tab" wire:click="pickTab('{{ $t['id'] }}')" wire:key="tab-{{ $t['id'] }}"
                style="display:flex;align-items:center;gap:8px;height:44px;padding:0 14px;border:none;background:transparent;font-family:inherit;font-size:14px;cursor:pointer;margin-bottom:-1px;
                {{ $tab === $t['id'] ? 'color:var(--color-text);font-weight:600;border-bottom:2px solid var(--color-primary);' : 'color:var(--color-text-muted);font-weight:500;border-bottom:2px solid transparent;' }}">
                {{ $t['label'] }}
                <span class="pill" style="{{ $tab === $t['id'] ? 'background:var(--color-primary);color:#fff;' : 'background:var(--color-bg);color:var(--color-text-muted);' }}min-width:20px;justify-content:center;">{{ $t['count'] }}</span>
            </button>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:1.25fr 1fr;gap:18px;align-items:start;">
        <section class="card" style="padding:0;overflow:hidden;">
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 150px 90px;gap:12px;padding:12px 18px;border-bottom:1px solid var(--color-border);font-size:11.5px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
                <span>Request</span><span>Stage</span><span style="text-align:right;">Waiting</span>
            </div>
            @forelse($visible as $it)
                <button type="button" wire:click="select('{{ $it['key'] }}')" wire:key="row-{{ $it['key'] }}"
                    style="width:100%;display:grid;grid-template-columns:minmax(0,1fr) 150px 90px;gap:12px;align-items:center;padding:13px 18px;border:none;border-bottom:1px solid var(--color-border);font-family:inherit;text-align:left;cursor:pointer;
                    {{ $selected && $selected['key'] === $it['key'] ? 'background:var(--color-primary-light);box-shadow:inset 3px 0 0 var(--color-primary);' : 'background:var(--color-surface);' }}">
                    <span style="display:flex;align-items:center;gap:12px;min-width:0;">
                        <span class="avatar" style="width:36px;height:36px;font-size:12px;{{ $it['kind'] === 'leave' ? 'background:var(--color-primary-light);color:var(--color-primary-dark);' : 'background:var(--color-accent-light);color:var(--color-accent);' }}">{{ $it['initials'] }}</span>
                        <span style="display:flex;flex-direction:column;min-width:0;line-height:1.35;">
                            <span style="font-size:14px;font-weight:600;color:var(--color-text);">{{ $it['who'] }}</span>
                            <span class="text-muted" style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <span class="pill {{ $it['kind'] === 'leave' ? 'pill-danger' : 'pill-success' }}" style="padding:2px 8px;">{{ $it['kindLabel'] }}</span> {{ $it['summary'] }}
                            </span>
                        </span>
                    </span>
                    <span class="text-muted" style="font-size:12.5px;">{{ $it['stage'] }}</span>
                    <span class="font-mono" style="text-align:right;font-size:12.5px;font-weight:600;">{{ $it['age'] }}</span>
                </button>
            @empty
                <p class="text-muted" style="padding:24px;">Nothing waiting in this queue.</p>
            @endforelse
        </section>

        <section class="card" style="padding:0;display:flex;flex-direction:column;">
            @if($selected)
                <div style="padding:20px 22px 16px;border-bottom:1px solid var(--color-border);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <span class="pill {{ $selected['kind'] === 'leave' ? 'pill-danger' : 'pill-success' }}">{{ $selected['kindLabel'] }}</span>
                        <span class="font-mono text-muted" style="font-size:12px;">{{ $selected['ref'] }}</span>
                    </div>
                    <h2 style="font-size:19px;">{{ $selected['title'] }}</h2>
                    <p class="text-muted" style="margin:4px 0 0;font-size:13px;">{{ $selected['who'] }} · {{ $selected['dept'] }}</p>
                </div>

                <div style="padding:16px 22px;display:flex;flex-direction:column;gap:10px;border-bottom:1px solid var(--color-border);">
                    @foreach($selected['facts'] as $f)
                        <div style="display:flex;justify-content:space-between;gap:16px;font-size:13.5px;">
                            <span class="text-muted">{{ $f['k'] }}</span><span style="font-weight:600;text-align:right;">{{ $f['v'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div style="padding:16px 22px;border-bottom:1px solid var(--color-border);">
                    <div style="font-size:11.5px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--color-text-faint);margin-bottom:12px;">Approval trail</div>
                    <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;">
                        @foreach($selected['trail'] as $s)
                            <li style="display:flex;align-items:center;gap:10px;">
                                <span class="trail-dot is-{{ $s['state'] }}"></span>
                                <span style="flex:1;font-size:13.5px;">{{ $s['label'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div style="padding:16px 22px;display:flex;flex-direction:column;gap:12px;">
                    <label for="cmt" style="font-size:13px;font-weight:600;">Comment <span class="text-muted" style="font-weight:400;">(sent with your decision)</span></label>
                    <textarea id="cmt" rows="2" wire:model="comment" placeholder="Add a note for the employee…"></textarea>
                    <div style="display:flex;gap:10px;">
                        <button type="button" wire:click="reject" class="btn btn-outline" style="flex:1;justify-content:center;">Reject</button>
                        <button type="button" wire:click="approve" class="btn btn-primary" style="flex:2;justify-content:center;">{{ $selected['primary'] }}</button>
                    </div>
                </div>
            @else
                <p class="text-muted" style="padding:24px;">Select an item from the list.</p>
            @endif
        </section>
    </div>
</div>
