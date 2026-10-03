<?php

use App\Models\AssetWarranty;
use App\Models\CompanyRegistrationDocumentVersion;
use App\Models\Renewable;
use App\Models\VehicleRenewal;
use Livewire\Component;

/**
 * Spec F2: "a single consolidated view across every consumer of the Renewal
 * & Compliance Reminder Engine (Section 3.2): Vehicle Renewals (E3), Asset
 * warranties (E2), and Company Registration Documents (E5), rather than
 * three separate places to check what's expiring soon." Admin/HR Admin only
 * — same audience as the underlying per-module configuration screens.
 */
new class extends Component
{
    private function label(Renewable $renewable): string
    {
        return match ($renewable->renewable_type) {
            AssetWarranty::class => ($renewable->renewable?->asset?->name ?? 'Asset').' — warranty',
            VehicleRenewal::class => trim(($renewable->renewable?->vehicle?->registration_number ?? 'Vehicle').' — '.($renewable->renewable?->label ?? 'renewal')),
            CompanyRegistrationDocumentVersion::class => $renewable->renewable?->document?->title ?? 'Company document',
            default => 'Item',
        };
    }

    public function with(): array
    {
        $renewables = Renewable::whereIn('status', ['active', 'expired'])
            ->with(['renewalType', 'renewable'])
            ->get()
            ->filter(fn (Renewable $r) => $r->status === 'expired' || $r->expiry_date->lte(now()->addDays(60)))
            ->sortBy('expiry_date')
            ->take(10)
            ->map(fn (Renewable $r) => [
                'label' => $this->label($r),
                'type' => $r->renewalType->label,
                'expiryDate' => $r->expiry_date,
                'isExpired' => $r->status === 'expired',
            ])
            ->values();

        return ['renewables' => $renewables];
    }
};
?>

<section class="card">
    <div class="card-header">
        <h2>Upcoming renewals &amp; compliance</h2>
        <span class="pill pill-neutral">{{ $renewables->count() }}</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;">
        @forelse($renewables as $r)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;background:var(--color-bg);border-radius:10px;">
                <div>
                    <div style="font-size:var(--fs-base);font-weight:600;">{{ $r['label'] }}</div>
                    <div class="text-muted" style="font-size:var(--fs-xs);">{{ $r['type'] }}</div>
                </div>
                <span class="pill {{ $r['isExpired'] ? 'pill-danger' : 'pill-warning' }}">
                    {{ $r['isExpired'] ? 'Expired '.$r['expiryDate']->format('j M Y') : 'Due '.$r['expiryDate']->format('j M Y') }}
                </span>
            </div>
        @empty
            <p class="text-muted">Nothing expiring in the next 60 days.</p>
        @endforelse
    </div>
</section>
