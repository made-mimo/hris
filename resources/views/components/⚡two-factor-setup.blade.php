<?php

use App\Services\TwoFactorService;
use Livewire\Component;

/**
 * The interactive body of /login/setup. Kept as a child component per the
 * "inert page + child Livewire component" rule (PLAN.md 4.3) — a full-page
 * SFC with its own wire:click reliably breaks on the second interaction in
 * Livewire 4, since the AJAX morph targets the whole document root.
 */
new class extends Component
{
    public string $step = 'choose'; // choose | totp | email | backup_codes

    public string $secret = '';

    public string $qrDataUri = '';

    public string $code = '';

    /** @var string[] */
    public array $backupCodes = [];

    public bool $trustDevice = true;

    /** @var string[] */
    public array $allowedMethods = [];

    public function mount(TwoFactorService $twoFactor): void
    {
        $user = auth()->user();

        if (! $twoFactor->isRequiredFor($user)
            || $twoFactor->isTrustedDeviceCookieValid($user, request()->cookie('trusted_device'))) {
            session(['two_factor_verified' => true]);
            $this->redirectRoute('home', navigate: true);

            return;
        }

        if ($user->hasTwoFactorEnrolled() && ! $twoFactor->needsReEnrollment($user)) {
            $this->redirectRoute('login.verify', navigate: true);

            return;
        }

        $this->allowedMethods = $twoFactor->allowedMethodsFor($user);
        $this->trustDevice = $twoFactor->trustedDeviceAllowedFor($user);

        // A role restricted to exactly one method skips the (pointless) choice screen.
        if (count($this->allowedMethods) === 1) {
            $this->allowedMethods[0] === 'totp' ? $this->chooseTotp($twoFactor) : $this->chooseEmail($twoFactor);
        }
    }

    public function chooseTotp(TwoFactorService $twoFactor): void
    {
        if (! in_array('totp', $twoFactor->allowedMethodsFor(auth()->user()), true)) {
            return;
        }

        $this->secret = $twoFactor->generateSecretKey();
        $this->qrDataUri = $twoFactor->qrCodeSvgDataUri(auth()->user()->email, $this->secret);
        $this->step = 'totp';
    }

    public function chooseEmail(TwoFactorService $twoFactor): void
    {
        if (! in_array('email', $twoFactor->allowedMethodsFor(auth()->user()), true)) {
            return;
        }

        $twoFactor->sendEmailCode(auth()->user());
        $this->step = 'email';
    }

    public function backToChoice(): void
    {
        $this->step = 'choose';
        $this->code = '';
        $this->resetErrorBag('code');
    }

    public function confirmTotp(TwoFactorService $twoFactor): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        if (! $twoFactor->confirmTotpEnrollment(auth()->user(), $this->secret, $this->code)) {
            $this->addError('code', 'That code didn\'t match — check the time on your device and try again.');

            return;
        }

        $this->backupCodes = $twoFactor->generateBackupCodes(auth()->user());
        $this->code = '';
        $this->step = 'backup_codes';
    }

    public function confirmEmail(TwoFactorService $twoFactor): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        if (! $twoFactor->verifyEmailCode(auth()->user(), $this->code)) {
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        $twoFactor->switchToEmail(auth()->user());
        $this->finish($twoFactor);
    }

    public function resendEmail(TwoFactorService $twoFactor): void
    {
        if ($twoFactor->canResendEmailCode(auth()->user())) {
            $twoFactor->sendEmailCode(auth()->user());
        }
    }

    public function finishEnrollment(TwoFactorService $twoFactor): void
    {
        $this->finish($twoFactor);
    }

    private function finish(TwoFactorService $twoFactor): void
    {
        session(['two_factor_verified' => true]);

        $user = auth()->user();

        if ($this->trustDevice && $twoFactor->trustedDeviceAllowedFor($user)) {
            $raw = $twoFactor->issueTrustedDevice($user, request()->userAgent(), request()->ip());
            cookie()->queue(cookie('trusted_device', $raw, 60 * 24 * $twoFactor->trustedDeviceDaysFor($user)));
        }

        $this->redirectRoute('home', navigate: true);
    }

    public function with(TwoFactorService $twoFactor): array
    {
        return [
            'trustedDeviceAllowed' => $twoFactor->trustedDeviceAllowedFor(auth()->user()),
            'trustedDeviceDays' => $twoFactor->trustedDeviceDaysFor(auth()->user()),
        ];
    }
};
?>

<div>
    @if($step === 'choose')
        <h1 style="font-size:28px;">Choose a sign-in method</h1>
        <p class="text-muted" style="margin:8px 0 24px;font-size:14px;line-height:1.55;">You can switch methods later from Account Security.</p>

        @if(in_array('totp', $allowedMethods, true))
            <button type="button" wire:click="chooseTotp" class="btn btn-outline" style="width:100%;justify-content:flex-start;gap:12px;height:64px;margin-bottom:12px;text-align:left;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"></rect><path d="M11 18h2"></path></svg>
                <span>
                    <span style="display:block;font-weight:700;font-size:14px;">Authenticator app</span>
                    <span style="display:block;font-size:12.5px;color:var(--color-text-muted);">Google Authenticator, Authy, 1Password, etc.</span>
                </span>
            </button>
        @endif

        @if(in_array('email', $allowedMethods, true))
            <button type="button" wire:click="chooseEmail" class="btn btn-outline" style="width:100%;justify-content:flex-start;gap:12px;height:64px;text-align:left;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg>
                <span>
                    <span style="display:block;font-weight:700;font-size:14px;">Email code</span>
                    <span style="display:block;font-size:12.5px;color:var(--color-text-muted);">We'll send a 6-digit code to {{ auth()->user()->email }}.</span>
                </span>
            </button>
        @endif
    @endif

    @if($step === 'totp')
        @if(count($allowedMethods) > 1)
            <button type="button" wire:click="backToChoice" class="auth-back-link" style="background:none;border:none;padding:0;cursor:pointer;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                Choose a different method
            </button>
        @endif

        <h1 style="font-size:26px;">Scan this QR code</h1>
        <p class="text-muted" style="margin:8px 0 20px;font-size:14px;line-height:1.55;">Open your authenticator app and scan the code, or enter the key manually.</p>

        <div style="display:flex;justify-content:center;padding:16px;background:#fff;border:1px solid var(--color-border);border-radius:12px;margin-bottom:14px;">
            <img src="{{ $qrDataUri }}" alt="QR code for two-factor setup" width="180" height="180">
        </div>
        <div class="hint" style="text-align:center;margin-bottom:22px;word-break:break-all;font-family:'Courier New',monospace;">{{ $secret }}</div>

        <form wire:submit="confirmTotp">
            <label for="code" style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;">Enter the 6-digit code to confirm</label>
            <input id="code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                   class="otp-input" style="width:100%;letter-spacing:0.4em;font-size:22px;margin-bottom:8px;" placeholder="••••••">
            @error('code') <div class="hint" style="color:var(--color-danger);margin-bottom:10px;">{{ $message }}</div> @enderror

            @if($trustedDeviceAllowed)
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid var(--color-border);border-radius:10px;background:var(--color-bg);cursor:pointer;margin:14px 0 24px;">
                    <input type="checkbox" wire:model="trustDevice" style="width:16px;height:16px;margin-top:2px;accent-color:var(--color-primary);">
                    <span>
                        <span style="display:block;font-size:13.5px;font-weight:600;">Trust this device for {{ $trustedDeviceDays }} days</span>
                        <span style="display:block;font-size:12.5px;color:var(--color-text-muted);">Skip this step on this browser until then.</span>
                    </span>
                </label>
            @endif

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;">Confirm and continue</button>
        </form>
    @endif

    @if($step === 'email')
        @if(count($allowedMethods) > 1)
            <button type="button" wire:click="backToChoice" class="auth-back-link" style="background:none;border:none;padding:0;cursor:pointer;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                Choose a different method
            </button>
        @endif

        <h1 style="font-size:26px;">Check your email</h1>
        <p class="text-muted" style="margin:8px 0 22px;font-size:14px;line-height:1.55;">
            We sent a 6-digit code to {{ auth()->user()->email }}. It expires in {{ config('twofactor.email_code_ttl_minutes') }} minutes.
        </p>

        <form wire:submit="confirmEmail">
            <label for="code" style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;">6-digit code</label>
            <input id="code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                   class="otp-input" style="width:100%;letter-spacing:0.4em;font-size:22px;margin-bottom:8px;" placeholder="••••••">
            @error('code') <div class="hint" style="color:var(--color-danger);margin-bottom:10px;">{{ $message }}</div> @enderror

            <button type="button" wire:click="resendEmail" class="btn btn-outline btn-sm" style="margin:6px 0 14px;">Resend code</button>

            @if($trustedDeviceAllowed)
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid var(--color-border);border-radius:10px;background:var(--color-bg);cursor:pointer;margin-bottom:24px;">
                    <input type="checkbox" wire:model="trustDevice" style="width:16px;height:16px;margin-top:2px;accent-color:var(--color-primary);">
                    <span>
                        <span style="display:block;font-size:13.5px;font-weight:600;">Trust this device for {{ $trustedDeviceDays }} days</span>
                        <span style="display:block;font-size:12.5px;color:var(--color-text-muted);">Skip this step on this browser until then.</span>
                    </span>
                </label>
            @endif

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;">Confirm and continue</button>
        </form>
    @endif

    @if($step === 'backup_codes')
        <h1 style="font-size:26px;">Save your backup codes</h1>
        <p class="text-muted" style="margin:8px 0 20px;font-size:14px;line-height:1.55;">
            Each code works once, if you ever lose access to your authenticator app. Store them somewhere safe — this is the only time they're shown.
        </p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:16px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:12px;margin-bottom:22px;font-family:'Courier New',monospace;font-size:14px;font-weight:600;">
            @foreach($backupCodes as $backupCode)
                <div>{{ $backupCode }}</div>
            @endforeach
        </div>

        <button type="button" wire:click="finishEnrollment" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;">I've saved these codes — continue</button>
    @endif
</div>
