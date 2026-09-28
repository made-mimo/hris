<?php

use App\Models\PolicyDocument;
use App\Services\PolicyService;
use App\Services\SignatureService;
use Livewire\Component;

/** Spec E4: "every employee can browse and download active policy documents they have access to" — non-restricted categories always, restricted only with an explicit grant. */
new class extends Component
{
    public function acknowledge(int $versionId, PolicyService $policy): void
    {
        $version = \App\Models\PolicyDocumentVersion::findOrFail($versionId);

        $policy->acknowledge(
            $version,
            auth()->user(),
            'click_to_sign',
            request()->ip(),
            request()->userAgent(),
        );

        session()->flash('status', 'Acknowledged.');
    }

    public function with(PolicyService $policy, SignatureService $signatures): array
    {
        $employee = auth()->user()->employee;
        $user = auth()->user();

        $documents = PolicyDocument::where('is_active', true)
            ->with(['category', 'versions'])
            ->get()
            ->filter(fn (PolicyDocument $doc) => $doc->currentVersion() && $policy->canAccessFile($doc->currentVersion(), $user))
            ->groupBy(fn (PolicyDocument $doc) => $doc->category->name);

        $outstanding = $employee ? $policy->outstandingFor($employee) : collect();

        return [
            'documentsByCategory' => $documents,
            'outstanding' => $outstanding,
            'signatures' => $signatures,
            'user' => $user,
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    @if($outstanding->isNotEmpty())
        <section class="rounded-md border border-warning/40 bg-warning-light p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">Outstanding acknowledgements</h2>
            <div class="flex flex-col gap-2.5">
                @foreach($outstanding as $doc)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-sm border border-border bg-surface px-3.5 py-2.5">
                        <div>
                            <div class="text-sm font-semibold text-text">{{ $doc->title }}</div>
                            <div class="text-xs text-text-muted">{{ $doc->category->name }} · version {{ $doc->currentVersion()->version_label }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('policies.file', $doc->currentVersion()) }}" target="_blank" class="text-xs font-semibold text-primary">View</a>
                            <button wire:click="acknowledge({{ $doc->currentVersion()->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">I acknowledge and agree</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @foreach($documentsByCategory as $categoryName => $docs)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">{{ $categoryName }}</h2>
            <div class="flex flex-col gap-2">
                @foreach($docs as $doc)
                    @php($version = $doc->currentVersion())
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2.5 last:border-0 last:pb-0">
                        <div>
                            <div class="text-sm font-medium text-text">{{ $doc->title }}</div>
                            <div class="text-xs text-text-muted">Version {{ $version->version_label }} — effective {{ $version->effective_date->format('j M Y') }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($version->isAcknowledgedBy($user, $signatures))
                                <span class="rounded-pill bg-accent-light px-2.5 py-1 text-xs font-semibold text-accent">Acknowledged</span>
                            @endif
                            <a href="{{ route('policies.file', $version) }}" target="_blank" class="text-xs font-semibold text-primary">Download</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    @if($documentsByCategory->isEmpty())
        <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No policy documents available.</div>
    @endif
</div>
