<?php

use App\Models\CandidateApplication;
use Livewire\Component;

/**
 * Spec D1's offer-letter e-signature: reached only via a signed URL (see
 * routes/web.php's 'signed' middleware) — the candidate has no login. Inert
 * page + child component per the mandatory pattern (PLAN.md 4.3) — the
 * actual signing form (wire:click) lives in offer-sign-form.blade.php.
 */
new class extends Component
{
    public CandidateApplication $application;

    public function mount(CandidateApplication $application): void
    {
        $this->application = $application;
    }
};
?>

<x-layouts.public title="Sign Your Offer Letter">
    <h1 class="font-display" style="font-size:22px;font-weight:800;margin-bottom:16px;">Offer of Employment</h1>

    <livewire:offer-sign-form :application="$application" />
</x-layouts.public>
