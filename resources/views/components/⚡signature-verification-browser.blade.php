<?php

use App\Models\SignatureEvent;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Spec Section A8: "an Admin/HR Admin-facing screen to look up who has and
 * has not signed a given document/version, and to export the full evidence
 * trail (hash, timestamp, IP, method) for a specific signature if ever
 * required for a dispute or audit." No consuming module exists yet (Policy
 * Documents E4, offer letters D1, etc. are all unbuilt), so this lists
 * whatever signature events any future module writes, filterable by purpose/
 * signer/date — the "who has and has not signed" cross-reference against a
 * specific document's expected-signer list is necessarily per-module (it
 * needs that module's own audience list), so it lands here once one exists.
 */
new class extends Component
{
    use WithPagination;

    public string $purpose = '';

    public string $signer = '';

    public ?int $expandedId = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function with(): array
    {
        $events = SignatureEvent::query()->with('signer')->latest('signed_at')
            ->when($this->purpose, fn ($q) => $q->where('purpose', 'like', "%{$this->purpose}%"))
            ->when($this->signer, fn ($q) => $q->whereHas('signer', fn ($q2) => $q2->where('email', 'like', "%{$this->signer}%")));

        return ['events' => $events->paginate(15)];
    }
};
?>

<section class="card" style="padding:0;overflow:hidden;">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:18px;border-bottom:1px solid var(--color-border);">
        <input type="text" wire:model.live.debounce.400ms="purpose" placeholder="Filter by purpose"
               style="flex:1;min-width:180px;padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:var(--fs-sm);">
        <input type="text" wire:model.live.debounce.400ms="signer" placeholder="Filter by signer email"
               style="flex:1;min-width:180px;padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;font-size:var(--fs-sm);">
    </div>

    <div style="display:grid;grid-template-columns:150px 1fr 160px 100px 40px;gap:12px;padding:10px 18px;border-bottom:1px solid var(--color-border);font-size:var(--fs-2xs);font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
        <span>Signed</span><span>Purpose / document</span><span>Signer</span><span>Method</span><span></span>
    </div>

    @forelse($events as $event)
        <div style="border-bottom:1px solid var(--color-border);">
            <div style="display:grid;grid-template-columns:150px 1fr 160px 100px 40px;gap:12px;align-items:center;padding:12px 18px;">
                <span class="font-mono text-muted" style="font-size:var(--fs-xs);">{{ $event->signed_at->format(\App\Support\Dates::DATE_TIME) }}</span>
                <span style="font-size:var(--fs-sm);">
                    <span style="font-weight:600;">{{ $event->purpose }}</span>
                    <span class="text-muted"> · {{ class_basename($event->signable_type) }} #{{ $event->signable_id }}</span>
                </span>
                <span style="font-size:var(--fs-sm);">{{ $event->signer?->email }}</span>
                <span class="pill pill-neutral">{{ $event->method === 'drawn' ? 'Drawn' : 'Click-to-sign' }}</span>
                <button type="button" wire:click="toggleExpand({{ $event->id }})" class="icon-btn" style="background:none;border:none;cursor:pointer;">
                    {{ $expandedId === $event->id ? '▲' : '▼' }}
                </button>
            </div>
            @if($expandedId === $event->id)
                <div style="padding:0 18px 16px;">
                    <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:8px;padding:12px 14px;font-family:'Courier New',monospace;font-size:var(--fs-xs);">
                        <div style="padding:3px 0;"><strong>Content hash</strong>: {{ $event->content_hash }}</div>
                        <div style="padding:3px 0;"><strong>IP address</strong>: {{ $event->ip_address ?? '—' }}</div>
                        <div style="padding:3px 0;"><strong>User agent</strong>: {{ $event->user_agent ?? '—' }}</div>
                        @if($event->method === 'drawn' && $event->signatureImageUrl())
                            <div style="padding:6px 0;"><img src="{{ $event->signatureImageUrl() }}" alt="Drawn signature" style="max-width:200px;background:#fff;border:1px solid var(--color-border);border-radius:6px;"></div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div class="hint" style="padding:24px 18px;">No signature events recorded yet — this fills in once a module that requires acknowledgement (policy documents, offer letters, review sign-off) exists.</div>
    @endforelse

    <div style="padding:14px 18px;">{{ $events->links() }}</div>
</section>
