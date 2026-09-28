<?php

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Spec Section 3.2/A2's "Audit log browser: filterable list (entity, action,
 * actor, date range) with per-entry field-level before/after drill-down."
 * Two tabs: generic entity-mutation log (App\Traits\Auditable) and the
 * login/security event log (spec A1) — deliberately separate mechanisms,
 * per spec 40 vs 86/101, surfaced together in one screen.
 */
new class extends Component
{
    use WithPagination;

    public string $tab = 'changes'; // changes | security

    public string $actor = '';

    public string $entityType = '';

    public string $securityEvent = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $expandedId = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->expandedId = null;
        $this->resetPage();
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function resetFilters(): void
    {
        $this->reset(['actor', 'entityType', 'securityEvent', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function with(): array
    {
        $changes = AuditLog::query()->with('actor')->latest('id')
            ->when($this->actor, fn ($q) => $q->where('actor_label', 'like', "%{$this->actor}%"))
            ->when($this->entityType, fn ($q) => $q->where('auditable_type', $this->entityType))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));

        $security = SecurityEvent::query()->with('user')->latest('id')
            ->when($this->actor, fn ($q) => $q->where('user_label', 'like', "%{$this->actor}%"))
            ->when($this->securityEvent, fn ($q) => $q->where('event', $this->securityEvent))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));

        return [
            'entityTypes' => AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'securityEventTypes' => SecurityEvent::query()->distinct()->orderBy('event')->pluck('event'),
            'changeRows' => $this->tab === 'changes' ? $changes->paginate(15, pageName: 'changes-page') : null,
            'securityRows' => $this->tab === 'security' ? $security->paginate(15, pageName: 'security-page') : null,
        ];
    }
};
?>

<section class="card" style="padding:0;overflow:hidden;">
    <div class="card-header" style="padding:18px 18px 0;">
        <div class="seg-switch" style="margin-bottom:16px;">
            <button type="button" wire:click="switchTab('changes')" class="seg-btn {{ $tab === 'changes' ? 'is-active' : '' }}">Data changes</button>
            <button type="button" wire:click="switchTab('security')" class="seg-btn {{ $tab === 'security' ? 'is-active' : '' }}">Security &amp; login</button>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:0 18px 16px;border-bottom:1px solid var(--color-border);">
        <input type="text" wire:model.live.debounce.400ms="actor" placeholder="Filter by actor email"
               style="flex:1;min-width:180px;padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:13px;">

        @if($tab === 'changes')
            <select wire:model.live="entityType" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:13px;">
                <option value="">All entities</option>
                @foreach($entityTypes as $type)
                    <option value="{{ $type }}">{{ class_basename($type) }}</option>
                @endforeach
            </select>
        @else
            <select wire:model.live="securityEvent" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:13px;">
                <option value="">All events</option>
                @foreach($securityEventTypes as $type)
                    <option value="{{ $type }}">{{ str($type)->replace('_', ' ')->ucfirst() }}</option>
                @endforeach
            </select>
        @endif

        <input type="date" wire:model.live="dateFrom" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:13px;">
        <span class="text-muted" style="font-size:12px;">to</span>
        <input type="date" wire:model.live="dateTo" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:13px;">

        <button type="button" wire:click="resetFilters" class="btn btn-outline btn-sm">Clear</button>
    </div>

    @if($tab === 'changes')
        <div style="display:grid;grid-template-columns:150px 100px 160px minmax(0,1fr) 40px;gap:12px;padding:10px 18px;border-bottom:1px solid var(--color-border);font-size:11px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
            <span>When</span><span>Action</span><span>Entity</span><span>Actor</span><span></span>
        </div>

        @forelse($changeRows as $row)
            <div style="border-bottom:1px solid var(--color-border);">
                <div style="display:grid;grid-template-columns:150px 100px 160px minmax(0,1fr) 40px;gap:12px;align-items:center;padding:12px 18px;">
                    <span class="font-mono text-muted" style="font-size:12.5px;">{{ $row->created_at->format(\App\Support\Dates::DATE_TIME) }}</span>
                    <span class="pill {{ match($row->action) { 'created' => 'pill-success', 'deleted' => 'pill-danger', default => 'pill-neutral' } }}">{{ $row->action }}</span>
                    <span style="font-size:13px;font-weight:600;">{{ $row->subjectLabel() }}</span>
                    <span style="font-size:13px;">{{ $row->actor_label ?? 'System' }}</span>
                    <button type="button" wire:click="toggleExpand({{ $row->id }})" class="icon-btn" style="background:none;border:none;cursor:pointer;">
                        {{ $expandedId === $row->id ? '▲' : '▼' }}
                    </button>
                </div>
                @if($expandedId === $row->id)
                    <div style="padding:0 18px 16px;">
                        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:8px;padding:12px 14px;font-family:'Courier New',monospace;font-size:12.5px;">
                            @if($row->action === 'updated')
                                @foreach($row->changes as $field => $pair)
                                    <div style="padding:3px 0;"><strong>{{ $field }}</strong>: {{ json_encode($pair[0]) }} &rarr; {{ json_encode($pair[1]) }}</div>
                                @endforeach
                            @else
                                @foreach($row->changes as $field => $value)
                                    <div style="padding:3px 0;"><strong>{{ $field }}</strong>: {{ json_encode($value) }}</div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="hint" style="padding:24px 18px;">No data changes match these filters.</div>
        @endforelse

        <div style="padding:14px 18px;">{{ $changeRows->links() }}</div>
    @else
        <div style="display:grid;grid-template-columns:150px 200px minmax(0,1fr) 100px 130px;gap:12px;padding:10px 18px;border-bottom:1px solid var(--color-border);font-size:11px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
            <span>When</span><span>Event</span><span>User</span><span>Method</span><span>IP</span>
        </div>

        @forelse($securityRows as $row)
            <div style="display:grid;grid-template-columns:150px 200px minmax(0,1fr) 100px 130px;gap:12px;align-items:center;padding:12px 18px;border-bottom:1px solid var(--color-border);">
                <span class="font-mono text-muted" style="font-size:12.5px;">{{ $row->created_at->format(\App\Support\Dates::DATE_TIME) }}</span>
                <span class="pill {{ str($row->event)->contains('failed') ? 'pill-danger' : 'pill-neutral' }}">{{ $row->label() }}</span>
                <span style="font-size:13px;">{{ $row->user_label ?? $row->metadata['email'] ?? '—' }}</span>
                <span style="font-size:12.5px;color:var(--color-text-muted);">{{ $row->method ?? '—' }}</span>
                <span class="font-mono text-muted" style="font-size:12px;">{{ $row->ip_address ?? '—' }}</span>
            </div>
        @empty
            <div class="hint" style="padding:24px 18px;">No security events match these filters.</div>
        @endforelse

        <div style="padding:14px 18px;">{{ $securityRows->links() }}</div>
    @endif
</section>
