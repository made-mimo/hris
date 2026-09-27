<?php

use App\Models\Setting;
use App\Services\EmployeeIdGenerator;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $twoFactorEnabled = false;
    public string $companyName = '';
    public $logo = null;

    public int $passwordMinLength = 8;
    public ?int $passwordMaxLength = null;
    public bool $passwordRequireUppercase = true;
    public bool $passwordRequireLowercase = true;
    public bool $passwordRequireNumber = true;
    public bool $passwordRequireSpecial = false;
    public bool $passwordAllowSpaces = true;

    public string $employeeIdFormat = 'SIL{YY}{MM}{SEQ:3}';
    public string $employeeIdSequenceScope = 'global';

    public ?string $orgTaxId = null;
    public ?string $orgRegistrationNumber = null;
    public ?string $orgAddress = null;
    public ?string $orgContactEmail = null;
    public ?string $orgContactPhone = null;

    public function mount(): void
    {
        $settings = Setting::current();
        $this->twoFactorEnabled = $settings->two_factor_enabled;
        $this->companyName = $settings->company_name;
        $this->passwordMinLength = $settings->password_min_length;
        $this->passwordMaxLength = $settings->password_max_length;
        $this->passwordRequireUppercase = $settings->password_require_uppercase;
        $this->passwordRequireLowercase = $settings->password_require_lowercase;
        $this->passwordRequireNumber = $settings->password_require_number;
        $this->passwordRequireSpecial = $settings->password_require_special;
        $this->passwordAllowSpaces = $settings->password_allow_spaces;
        $this->employeeIdFormat = $settings->employee_id_format;
        $this->employeeIdSequenceScope = $settings->employee_id_sequence_scope;
        $this->orgTaxId = $settings->tax_id;
        $this->orgRegistrationNumber = $settings->registration_number;
        $this->orgAddress = $settings->address;
        $this->orgContactEmail = $settings->contact_email;
        $this->orgContactPhone = $settings->contact_phone;
    }

    public function getEmployeeIdPreviewProperty(): string
    {
        try {
            return app(EmployeeIdGenerator::class)->preview($this->employeeIdFormat);
        } catch (\Throwable) {
            return '—';
        }
    }

    public function saveEmployeeIdFormat(): void
    {
        $this->validate([
            'employeeIdFormat' => ['required', 'string', 'max:100', 'regex:/\{SEQ(:\d+)?\}/'],
            'employeeIdSequenceScope' => ['required', 'in:global,per_year,per_month'],
        ], [
            'employeeIdFormat.regex' => 'The format must include a {SEQ} or {SEQ:n} token.',
        ]);

        Setting::current()->update([
            'employee_id_format' => $this->employeeIdFormat,
            'employee_id_sequence_scope' => $this->employeeIdSequenceScope,
        ]);

        Setting::forget();
        session()->flash('status', 'Employee ID format updated — existing employees keep their current IDs; only new hires use the new format.');
    }

    public function updatedTwoFactorEnabled($value): void
    {
        Setting::current()->update(['two_factor_enabled' => $value]);
        Setting::forget();
        session()->flash('status', $value
            ? 'Two-factor authentication is now required project-wide.'
            : 'Two-factor authentication is now switched off project-wide — for local dev/testing only.');
    }

    public function savePasswordPolicy(): void
    {
        $this->validate([
            'passwordMinLength' => ['required', 'integer', 'min:4', 'max:128'],
            'passwordMaxLength' => ['nullable', 'integer', 'gte:passwordMinLength', 'max:255'],
        ]);

        Setting::current()->update([
            'password_min_length' => $this->passwordMinLength,
            'password_max_length' => $this->passwordMaxLength,
            'password_require_uppercase' => $this->passwordRequireUppercase,
            'password_require_lowercase' => $this->passwordRequireLowercase,
            'password_require_number' => $this->passwordRequireNumber,
            'password_require_special' => $this->passwordRequireSpecial,
            'password_allow_spaces' => $this->passwordAllowSpaces,
        ]);

        Setting::forget();
        session()->flash('status', 'Password policy updated — anyone whose password no longer meets it will be asked to change it at next login.');
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

    public function saveOrgProfile(): void
    {
        $this->validate([
            'orgTaxId' => ['nullable', 'string', 'max:100'],
            'orgRegistrationNumber' => ['nullable', 'string', 'max:100'],
            'orgAddress' => ['nullable', 'string', 'max:1000'],
            'orgContactEmail' => ['nullable', 'email', 'max:255'],
            'orgContactPhone' => ['nullable', 'string', 'max:50'],
        ]);

        Setting::current()->update([
            'tax_id' => $this->orgTaxId ?: null,
            'registration_number' => $this->orgRegistrationNumber ?: null,
            'address' => $this->orgAddress ?: null,
            'contact_email' => $this->orgContactEmail ?: null,
            'contact_phone' => $this->orgContactPhone ?: null,
        ]);

        Setting::forget();
        session()->flash('status', 'Organization profile updated.');
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
                Currently <strong class="text-text">{{ $twoFactorEnabled ? 'ON' : 'OFF' }}</strong>. This is the project-wide switch; per-role refinements (required methods, trusted-device skip) are on each role's own page under <a href="{{ route('admin.roles') }}" wire:navigate class="font-semibold text-primary">Roles &amp; Permissions</a>.
            </div>
        </section>

        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <div class="mb-3.5 flex items-center justify-between">
                <h2 class="font-display text-base font-bold text-text">Password policy</h2>
            </div>
            <form wire:submit="savePasswordPolicy" class="flex flex-col gap-3.5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="passwordMinLength" class="mb-1.5 block text-xs font-semibold text-text">Minimum length</label>
                        <input id="passwordMinLength" type="number" wire:model="passwordMinLength" min="4" max="128"
                            class="w-full rounded-sm border border-border bg-surface px-3 py-2 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                        @error('passwordMinLength') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="passwordMaxLength" class="mb-1.5 block text-xs font-semibold text-text">Maximum length</label>
                        <input id="passwordMaxLength" type="number" wire:model="passwordMaxLength" min="4" max="255" placeholder="No limit"
                            class="w-full rounded-sm border border-border bg-surface px-3 py-2 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                        @error('passwordMaxLength') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 text-xs text-text">
                        <input type="checkbox" wire:model="passwordRequireUppercase" class="h-4 w-4 accent-primary">
                        Require uppercase letter
                    </label>
                    <label class="flex items-center gap-2 text-xs text-text">
                        <input type="checkbox" wire:model="passwordRequireLowercase" class="h-4 w-4 accent-primary">
                        Require lowercase letter
                    </label>
                    <label class="flex items-center gap-2 text-xs text-text">
                        <input type="checkbox" wire:model="passwordRequireNumber" class="h-4 w-4 accent-primary">
                        Require a number
                    </label>
                    <label class="flex items-center gap-2 text-xs text-text">
                        <input type="checkbox" wire:model="passwordRequireSpecial" class="h-4 w-4 accent-primary">
                        Require a special character
                    </label>
                    <label class="flex items-center gap-2 text-xs text-text">
                        <input type="checkbox" wire:model="passwordAllowSpaces" class="h-4 w-4 accent-primary">
                        Allow spaces
                    </label>
                </div>

                <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Save password policy</button>
            </form>
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

        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <div class="mb-3.5 flex items-center justify-between">
                <h2 class="font-display text-base font-bold text-text">Employee ID format</h2>
            </div>
            <form wire:submit="saveEmployeeIdFormat" class="flex flex-col gap-3.5">
                <div>
                    <label for="employeeIdFormat" class="mb-1.5 block text-sm font-semibold text-text">Format template</label>
                    <input id="employeeIdFormat" type="text" wire:model.live="employeeIdFormat"
                        class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 font-mono text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                    @error('employeeIdFormat') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    <div class="mt-1 text-xs text-text-muted">Tokens: <code>{YY}</code>, <code>{MM}</code>, <code>{SEQ:n}</code> — literal text and order are otherwise up to you.</div>
                </div>

                <div class="rounded-sm border border-border bg-bg px-3.5 py-2.5 font-mono text-sm text-text">
                    Preview: {{ $this->employeeIdPreview }}
                </div>

                <div>
                    <label for="employeeIdSequenceScope" class="mb-1.5 block text-sm font-semibold text-text">Sequence counter</label>
                    <select id="employeeIdSequenceScope" wire:model="employeeIdSequenceScope"
                        class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                        <option value="global">Global — one continuous count, never resets (SI's confirmed policy)</option>
                        <option value="per_year">Per year — resets to 1 every January</option>
                        <option value="per_month">Per month — resets to 1 every month</option>
                    </select>
                </div>

                <div class="text-xs text-text-muted">Forward-only: changing this only affects employees added after the change — every existing employee keeps their current ID.</div>

                <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Save Employee ID format</button>
            </form>
        </section>

        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <div class="mb-3.5 flex items-center justify-between">
                <h2 class="font-display text-base font-bold text-text">Organization profile</h2>
            </div>
            <form wire:submit="saveOrgProfile" class="flex flex-col gap-3.5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="orgTaxId" class="mb-1.5 block text-xs font-semibold text-text">Tax ID</label>
                        <input id="orgTaxId" type="text" wire:model="orgTaxId"
                            class="w-full rounded-sm border border-border bg-surface px-3 py-2 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                        @error('orgTaxId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="orgRegistrationNumber" class="mb-1.5 block text-xs font-semibold text-text">Registration number</label>
                        <input id="orgRegistrationNumber" type="text" wire:model="orgRegistrationNumber"
                            class="w-full rounded-sm border border-border bg-surface px-3 py-2 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                        @error('orgRegistrationNumber') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div>
                    <label for="orgAddress" class="mb-1.5 block text-sm font-semibold text-text">Address</label>
                    <textarea id="orgAddress" wire:model="orgAddress" rows="2"
                        class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light"></textarea>
                    @error('orgAddress') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="orgContactEmail" class="mb-1.5 block text-xs font-semibold text-text">Contact email</label>
                        <input id="orgContactEmail" type="email" wire:model="orgContactEmail"
                            class="w-full rounded-sm border border-border bg-surface px-3 py-2 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                        @error('orgContactEmail') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="orgContactPhone" class="mb-1.5 block text-xs font-semibold text-text">Contact phone</label>
                        <input id="orgContactPhone" type="text" wire:model="orgContactPhone"
                            class="w-full rounded-sm border border-border bg-surface px-3 py-2 font-body text-sm text-text outline-none focus:border-primary focus:ring-3 focus:ring-primary-light">
                        @error('orgContactPhone') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>

                <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Save organization profile</button>
            </form>
        </section>
    </div>
</div>
