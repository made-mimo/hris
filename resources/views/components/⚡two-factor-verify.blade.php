<?php

use App\Models\SecurityEvent;
use App\Services\TwoFactorService;
use Livewire\Component;

/**
 * The interactive body of /login/verify. Kept as a child component per the
 * "inert page + child Livewire component" rule (PLAN.md 4.3) — a full-page
 * SFC with its own wire:click reliably breaks on the second interaction in
 * Livewire 4, since the AJAX morph targets the whole document root.
 */
new class extends Component
{
    public string $method = 'totp';

    public string $code = '';

    public bool $useBackupCode = false;

    public bool $trustDevice = true;

    public function mount(TwoFactorService $twoFactor): void
    {
        $user = auth()->user();

        if (! $twoFactor->isRequiredFor($user)
            || $twoFactor->isTrustedDeviceCookieValid($user, request()->cookie('trusted_device'))) {
            session(['two_factor_verified' => true]);
            $this->redirectRoute('home', navigate: true);

            return;
        }

        if (! $user->hasTwoFactorEnrolled() || $twoFactor->needsReEnrollment($user)) {
            $this->redirectRoute('login.setup', navigate: true);

            return;
        }

        $this->method = $user->two_factor_method;
        $this->trustDevice = $twoFactor->trustedDeviceAllowedFor($user);

        if ($this->method === 'email') {
            $twoFactor->sendEmailCode($user);
        }
    }

    public function verify(TwoFactorService $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string']]);

        $user = auth()->user();

        $ok = match (true) {
            $this->useBackupCode => $twoFactor->verifyBackupCode($user, $this->code),
            $this->method === 'totp' => $twoFactor->verifyTotp($user, $this->code),
            $this->method === 'email' => $twoFactor->verifyEmailCode($user, $this->code),
            default => false,
        };

        if (! $ok) {
            SecurityEvent::record('two_factor_verify_failed', $user, $this->useBackupCode ? 'backup_code' : $this->method);
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        SecurityEvent::record('two_factor_verified', $user, $this->useBackupCode ? 'backup_code' : $this->method);

        session(['two_factor_verified' => true]);

        if ($this->trustDevice && $twoFactor->trustedDeviceAllowedFor($user)) {
            $raw = $twoFactor->issueTrustedDevice($user, request()->userAgent(), request()->ip());
            cookie()->queue(cookie('trusted_device', $raw, 60 * 24 * $twoFactor->trustedDeviceDaysFor($user)));
        }

        $this->redirectRoute('home', navigate: true);
    }

    public function resendEmail(TwoFactorService $twoFactor): void
    {
        if ($this->method === 'email' && $twoFactor->canResendEmailCode(auth()->user())) {
            $twoFactor->sendEmailCode(auth()->user());
        }
    }

    public function toggleBackupCode(): void
    {
        $this->useBackupCode = ! $this->useBackupCode;
        $this->code = '';
        $this->resetErrorBag('code');
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
    <form wire:submit="verify">
        <a href="{{ route('login') }}" wire:navigate class="auth-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
            Back to sign in
        </a>

        <div style="width:52px;height:52px;border-radius:14px;background:var(--color-primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"></path><path d="m9 12 2 2 4-4"></path></svg>
        </div>
        <h1 style="font-size:28px;">Verify it's you</h1>

        @if($useBackupCode)
            <p class="text-muted" style="margin:8px 0 22px;font-size:14px;line-height:1.55;">Enter one of your unused backup codes.</p>
        @elseif($method === 'totp')
            <p class="text-muted" style="margin:8px 0 22px;font-size:14px;line-height:1.55;">Enter the 6-digit code from your authenticator app for {{ auth()->user()->email }}.</p>
        @else
            <p class="text-muted" style="margin:8px 0 22px;font-size:14px;line-height:1.55;">
                We sent a 6-digit code to {{ \Illuminate\Support\Str::mask(auth()->user()->email, '•', 1, strpos(auth()->user()->email, '@') - 1) }}. It expires in {{ config('twofactor.email_code_ttl_minutes') }} minutes.
            </p>
        @endif

        <label for="code" style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;">{{ $useBackupCode ? 'Backup code' : '6-digit code' }}</label>
        @if($useBackupCode)
            <input id="code" wire:model="code" autocomplete="one-time-code"
                   class="otp-input" style="width:100%;letter-spacing:0.2em;font-size:18px;margin-bottom:8px;text-transform:uppercase;" placeholder="XXXX-XXXX">
        @else
            <input id="code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                   class="otp-input" style="width:100%;letter-spacing:0.4em;font-size:22px;margin-bottom:8px;" placeholder="••••••">
        @endif
        @error('code') <div class="hint" style="color:var(--color-danger);margin-bottom:10px;">{{ $message }}</div> @enderror

        <div style="display:flex;justify-content:space-between;margin-bottom:18px;">
            @if($method === 'email' && ! $useBackupCode)
                <button type="button" wire:click="resendEmail" class="hint" style="background:none;border:none;padding:0;cursor:pointer;font-weight:600;">Resend code</button>
            @else
                <span></span>
            @endif

            @if($method === 'totp')
                <button type="button" wire:click="toggleBackupCode" class="hint" style="background:none;border:none;padding:0;cursor:pointer;font-weight:600;">
                    {{ $useBackupCode ? 'Use authenticator code instead' : 'Use a backup code instead' }}
                </button>
            @endif
        </div>

        @if($trustedDeviceAllowed)
            <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid var(--color-border);border-radius:10px;background:var(--color-bg);cursor:pointer;margin-bottom:24px;">
                <input type="checkbox" wire:model="trustDevice" style="width:16px;height:16px;margin-top:2px;accent-color:var(--color-primary);">
                <span>
                    <span style="display:block;font-size:13.5px;font-weight:600;">Trust this device for {{ $trustedDeviceDays }} days</span>
                    <span style="display:block;font-size:12.5px;color:var(--color-text-muted);">Skip this step on this browser. Revoke anytime in Account Security.</span>
                </span>
            </label>
        @else
            <div class="hint" style="margin-bottom:24px;">Your role requires this step at every sign-in — trusted devices aren't available.</div>
        @endif

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;">Verify and continue</button>
    </form>
</div>
