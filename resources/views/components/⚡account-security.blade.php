<?php

use App\Services\TwoFactorService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Self-service 2FA management (spec A1): switch method, regenerate backup
 * codes, and manage trusted devices. Shown on the Profile page regardless of
 * the project-wide Setting::twoFactorEnabled() switch, so people can set
 * this up ahead of time even while it isn't enforced yet in this dev phase.
 * Method choices and trusted-device availability are further constrained by
 * the signed-in user's role policy (Role's two_factor_* columns).
 */
new class extends Component
{
    public bool $switchingToTotp = false;

    /** #[Locked] — confirmTotpSwitch() confirms enrollment against this exact value; see ⚡two-factor-setup.blade.php's identical fix for why. */
    #[Locked]
    public string $secret = '';

    #[Locked]
    public string $qrDataUri = '';

    public string $code = '';

    /** @var string[] */
    #[Locked]
    public array $newBackupCodes = [];

    public function startTotpSwitch(TwoFactorService $twoFactor): void
    {
        if (! in_array('totp', $twoFactor->allowedMethodsFor(auth()->user()), true)) {
            return;
        }

        $this->secret = $twoFactor->generateSecretKey();
        $this->qrDataUri = $twoFactor->qrCodeSvgDataUri(auth()->user()->email, $this->secret);
        $this->switchingToTotp = true;
        $this->newBackupCodes = [];
    }

    public function cancelTotpSwitch(): void
    {
        $this->switchingToTotp = false;
        $this->secret = '';
        $this->qrDataUri = '';
        $this->code = '';
        $this->resetErrorBag('code');
    }

    public function confirmTotpSwitch(TwoFactorService $twoFactor): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        if (! $twoFactor->confirmTotpEnrollment(auth()->user(), $this->secret, $this->code)) {
            $this->addError('code', 'That code didn\'t match — check the time on your device and try again.');

            return;
        }

        $this->newBackupCodes = $twoFactor->generateBackupCodes(auth()->user());
        $this->switchingToTotp = false;
        $this->code = '';
        session()->flash('status', 'Authenticator app is now your sign-in method.');
    }

    public function switchToEmail(TwoFactorService $twoFactor): void
    {
        if (! in_array('email', $twoFactor->allowedMethodsFor(auth()->user()), true)) {
            return;
        }

        $twoFactor->switchToEmail(auth()->user());
        $this->newBackupCodes = [];
        session()->flash('status', 'Email code is now your sign-in method.');
    }

    public function regenerateBackupCodes(TwoFactorService $twoFactor): void
    {
        if (auth()->user()->two_factor_method !== 'totp') {
            return;
        }

        $this->newBackupCodes = $twoFactor->generateBackupCodes(auth()->user());
    }

    public function dismissBackupCodes(): void
    {
        $this->newBackupCodes = [];
    }

    public function revokeDevice(int $id, TwoFactorService $twoFactor): void
    {
        $twoFactor->revokeTrustedDevice(auth()->user(), $id);
    }

    public function revokeAllDevices(TwoFactorService $twoFactor): void
    {
        $twoFactor->revokeAllTrustedDevices(auth()->user());
    }

    public function with(TwoFactorService $twoFactor): array
    {
        $user = auth()->user()->fresh();
        $allowedMethods = $twoFactor->allowedMethodsFor($user);

        return [
            'method' => $user->two_factor_method,
            'enrolled' => $user->hasTwoFactorEnrolled(),
            'twoFactorRequired' => $twoFactor->isRequiredFor($user),
            'needsReEnrollment' => $twoFactor->needsReEnrollment($user),
            'allowedMethods' => $allowedMethods,
            'trustedDeviceAllowed' => $twoFactor->trustedDeviceAllowedFor($user),
            'trustedDeviceDays' => $twoFactor->trustedDeviceDaysFor($user),
            'backupCodesRemaining' => $user->backupCodes()->whereNull('used_at')->count(),
            'devices' => $user->trustedDevices()->orderByDesc('last_used_at')->get(),
        ];
    }
};
?>

<section class="card">
    <div class="card-header"><h2>Account security</h2></div>

    @if(session('status'))
        <div class="pill pill-success" style="margin-bottom:16px;padding:10px 14px;">{{ session('status') }}</div>
    @endif

    @if(! $twoFactorRequired)
        <div class="hint" style="margin-bottom:16px;">Two-factor sign-in isn't required by your organization right now, but you can still set it up.</div>
    @endif

    @if($needsReEnrollment)
        <div class="pill pill-warning" style="margin-bottom:16px;padding:10px 14px;">Your role's 2FA policy changed and no longer allows your current method — choose a new one below.</div>
    @endif

    @if((! $enrolled || $needsReEnrollment) && ! $switchingToTotp)
        @if(! $enrolled)
            <p class="text-muted" style="font-size:13.5px;margin-bottom:14px;">You haven't set up two-factor sign-in yet.</p>
        @endif
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            @if(in_array('totp', $allowedMethods, true))
                <button type="button" wire:click="startTotpSwitch" class="btn btn-outline btn-sm">Set up authenticator app</button>
            @endif
            @if(in_array('email', $allowedMethods, true))
                <button type="button" wire:click="switchToEmail" class="btn btn-outline btn-sm">Use email code instead</button>
            @endif
        </div>
    @elseif($switchingToTotp)
        <h3 style="font-size:15px;margin-bottom:10px;">Scan this QR code</h3>
        <div style="display:flex;justify-content:center;padding:16px;background:#fff;border:1px solid var(--color-border);border-radius:12px;margin-bottom:12px;max-width:220px;">
            <img src="{{ $qrDataUri }}" alt="QR code for two-factor setup" width="180" height="180">
        </div>
        <div class="hint" style="margin-bottom:16px;word-break:break-all;font-family:'Courier New',monospace;">{{ $secret }}</div>

        <form wire:submit="confirmTotpSwitch" style="max-width:260px;">
            <label for="ts-code" style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;">Enter the 6-digit code to confirm</label>
            <input id="ts-code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                   class="otp-input" style="width:100%;letter-spacing:0.4em;font-size:20px;margin-bottom:8px;" placeholder="••••••">
            @error('code') <div class="hint" style="color:var(--color-danger);margin-bottom:10px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                <button type="button" wire:click="cancelTotpSwitch" class="btn btn-outline btn-sm">Cancel</button>
            </div>
        </form>
    @else
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <div>
                <div style="font-size:13.5px;font-weight:600;">
                    {{ $method === 'totp' ? 'Authenticator app' : 'Email code' }}
                </div>
                <div class="hint">
                    @if($method === 'totp')
                        {{ $backupCodesRemaining }} unused backup {{ \Illuminate\Support\Str::plural('code', $backupCodesRemaining) }} remaining.
                    @else
                        Codes are sent to {{ auth()->user()->email }}.
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                @if($method === 'totp')
                    @if(in_array('email', $allowedMethods, true))
                        <button type="button" wire:click="switchToEmail" class="btn btn-outline btn-sm">Switch to email</button>
                    @endif
                    <button type="button" wire:click="regenerateBackupCodes" class="btn btn-outline btn-sm">Regenerate backup codes</button>
                @else
                    @if(in_array('totp', $allowedMethods, true))
                        <button type="button" wire:click="startTotpSwitch" class="btn btn-outline btn-sm">Switch to authenticator app</button>
                    @endif
                @endif
            </div>
        </div>
    @endif

    @if(! empty($newBackupCodes))
        <div style="padding:16px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:12px;margin-top:6px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <h3 style="font-size:14px;margin:0;">New backup codes</h3>
                <button type="button" wire:click="dismissBackupCodes" class="hint" style="background:none;border:none;padding:0;cursor:pointer;font-weight:600;">Done</button>
            </div>
            <p class="hint" style="margin-bottom:12px;">Save these now — each works once, and this is the only time they're shown. Your old codes no longer work.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-family:'Courier New',monospace;font-size:13px;font-weight:600;">
                @foreach($newBackupCodes as $backupCode)
                    <div>{{ $backupCode }}</div>
                @endforeach
            </div>
        </div>
    @endif

    @if($enrolled)
        <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--color-border);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <h3 style="font-size:14px;margin:0;">Trusted devices</h3>
                @if($devices->isNotEmpty())
                    <button type="button" wire:click="revokeAllDevices" class="hint" style="background:none;border:none;padding:0;cursor:pointer;font-weight:600;color:var(--color-danger);">Revoke all</button>
                @endif
            </div>

            @unless($trustedDeviceAllowed)
                <div class="hint" style="margin-bottom:12px;">Your role requires 2FA at every sign-in — new devices can no longer be trusted, though any listed below still work until they expire or are revoked.</div>
            @endunless

            @forelse($devices as $device)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--color-border);font-size:13px;">
                    <div>
                        <div style="font-weight:600;">{{ $device->label ?: $device->deviceGuess() }}</div>
                        <div class="hint">
                            Trusted {{ $device->trusted_at->format('j M Y') }} ·
                            {{ $device->expires_at->isPast() ? 'Expired' : 'Expires '.$device->expires_at->format('j M Y') }}
                            @if($device->last_used_at) · Last used {{ $device->last_used_at->diffForHumans() }} @endif
                        </div>
                    </div>
                    <button type="button" wire:click="revokeDevice({{ $device->id }})" class="btn btn-outline btn-sm">Revoke</button>
                </div>
            @empty
                <div class="hint">No trusted devices yet.</div>
            @endforelse
        </div>
    @endif
</section>
