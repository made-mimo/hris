<?php

use App\Models\Setting;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $twoFactorEnabled = false;
    public string $companyName = '';
    public $logo = null;

    public function mount(): void
    {
        $settings = Setting::current();
        $this->twoFactorEnabled = $settings->two_factor_enabled;
        $this->companyName = $settings->company_name;
    }

    public function updatedTwoFactorEnabled($value): void
    {
        Setting::current()->update(['two_factor_enabled' => $value]);
        Setting::forget();
        session()->flash('status', $value
            ? 'Two-factor authentication is now required project-wide.'
            : 'Two-factor authentication is now switched off project-wide — for local dev/testing only.');
    }

    public function saveBranding(): void
    {
        $this->validate([
            'companyName' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = Setting::current();
        $settings->update(['company_name' => $this->companyName]);

        if ($this->logo) {
            $settings->addMedia($this->logo->getRealPath())
                ->usingName($this->logo->getClientOriginalName())
                ->toMediaCollection('logo');
        }

        Setting::forget();
        $this->reset('logo');
        session()->flash('status', 'Branding updated.');
    }

    public function with(): array
    {
        return ['currentLogoUrl' => Setting::current()->logoUrl()];
    }
};
?>

{{--
    Styled with Tailwind utility classes directly (spec Section 3.5), rather
    than the theme.css component classes the six artifact-matched screens use
    — this page has no pixel-reference to preserve, so it's the pattern to
    follow for every *new* screen going forward. See PLAN.md for why the
    artifact screens keep their existing, already-verified theme.css classes
    instead of being rewritten under time pressure.
--}}
<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <div class="grid items-start gap-4 md:grid-cols-2">
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <div class="mb-3.5 flex items-center justify-between">
                <h2 class="font-display text-base font-bold text-text">Two-factor authentication</h2>
            </div>
            <label class="flex cursor-pointer items-start gap-3 rounded-[10px] border border-border p-3.5">
                <input type="checkbox" wire:model.live="twoFactorEnabled" class="mt-0.5 h-[18px] w-[18px] accent-primary">
                <span>
                    <span class="block text-sm font-semibold text-text">Require 2FA at login, project-wide</span>
                    <span class="mt-0.5 block text-xs text-text-muted">
                        When off, every user skips straight from password to the app — useful during development/testing so login codes aren't required. Turn this back on before anything resembling production use.
                    </span>
                </span>
            </label>
            <div class="mt-3 text-xs text-text-muted">
                Currently <strong class="text-text">{{ $twoFactorEnabled ? 'ON' : 'OFF' }}</strong>. This is a single project-wide switch — the spec's per-role policy control (Section A1) is future work.
            </div>
        </section>

        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <div class="mb-3.5 flex items-center justify-between">
                <h2 class="font-display text-base font-bold text-text">Branding</h2>
            </div>
            <form wire:submit="saveBranding" class="flex flex-col gap-4">
                <div>
                    <label for="companyName" class="mb-1.5 block text-sm font-semibold text-text">Company name</label>
                    <input id="companyName" type="text" wire:model="companyName"
                        class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 font-body text-sm text-text outline-none transition-colors focus:border-primary focus:ring-3 focus:ring-primary-light">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-text">Logo</label>
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-md border border-border bg-bg">
                            @if($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="h-full w-full object-cover">
                            @elseif($currentLogoUrl)
                                <img src="{{ $currentLogoUrl }}" alt="Current logo" class="h-full w-full object-cover">
                            @else
                                <span class="text-[11px] text-text-faint">No logo</span>
                            @endif
                        </div>
                        <input type="file" wire:model="logo" accept="image/*" class="text-sm text-text">
                    </div>
                    @error('logo') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    <div class="mt-1 text-xs text-text-muted">PNG or SVG, up to 2 MB. Shown in the sidebar in place of the default mark.</div>
                </div>

                <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Save branding</button>
            </form>
        </section>
    </div>
</div>
