<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\TrustedDevice;
use App\Models\TwoFactorBackupCode;
use App\Models\TwoFactorEmailCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Spec Section A1's full 2FA implementation: TOTP (authenticator app) with
 * QR enrollment and single-use backup codes, an emailed one-time-code
 * alternative that needs no enrollment step, and trusted-device skip — all
 * user-selectable at enrollment and switchable later from Account Security.
 *
 * The project-wide on/off switch lives at Setting::twoFactorEnabled() (Section
 * A5) and is left OFF by default through this dev phase — when off, 2FA is
 * off for everyone, full stop. When on, the per-role policy columns on
 * `roles` (spec A1's "Admin policy control") refine it further: whether a
 * given role is required to enroll at all, which method(s) it's allowed to
 * use, whether it may skip via a trusted device, and for how long.
 */
class TwoFactorService
{
    public function __construct(private Google2FA $engine = new Google2FA) {}

    /** Master switch AND this user's role both say 2FA applies to them. */
    public function isRequiredFor(User $user): bool
    {
        return Setting::twoFactorEnabled() && (bool) $user->role?->two_factor_required;
    }

    /** @return string[] */
    public function allowedMethodsFor(User $user): array
    {
        return $user->role?->twoFactorAllowedMethods() ?? ['totp', 'email'];
    }

    public function trustedDeviceAllowedFor(User $user): bool
    {
        return (bool) ($user->role?->two_factor_trusted_device_allowed ?? true);
    }

    public function trustedDeviceDaysFor(User $user): int
    {
        return $user->role?->twoFactorTrustedDeviceDays() ?? config('twofactor.trusted_device_days');
    }

    /** Enrolled, but in a method their role no longer permits — must re-enroll before they can verify. */
    public function needsReEnrollment(User $user): bool
    {
        return $user->hasTwoFactorEnrolled()
            && ! in_array($user->two_factor_method, $this->allowedMethodsFor($user), true);
    }

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * An inline `data:image/svg+xml;base64,...` QR code — no Imagick
     * extension is installed in this environment, so the Bacon backend
     * (bacon/bacon-qr-code, pulled in transitively) automatically falls
     * back to its SVG renderer, which returns raw markup rather than a
     * ready-made data URI; we wrap it ourselves for a plain <img src>.
     */
    public function qrCodeSvgDataUri(string $holder, string $secret, int $size = 200): string
    {
        $svg = $this->engine->getQRCodeInline(
            config('app.name', 'Systems Intelligenz HRIS'),
            $holder,
            $secret,
            $size
        );

        if (str_starts_with($svg, 'data:')) {
            return $svg;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function verifyTotp(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return $this->engine->verifyKey($user->two_factor_secret, $code, 1) !== false;
    }

    /**
     * Verifies a freshly generated (not-yet-persisted) secret against the
     * code the user typed back from their authenticator app, and only then
     * commits the secret + method + confirmation timestamp — so a user who
     * mis-scans the QR never ends up locked into a secret they can't produce
     * codes for.
     */
    public function confirmTotpEnrollment(User $user, string $secret, string $code): bool
    {
        if ($this->engine->verifyKey($secret, $code, 1) === false) {
            return false;
        }

        $event = $user->hasTwoFactorEnrolled() ? 'two_factor_method_changed' : 'two_factor_enrolled';

        $user->forceFill([
            'two_factor_method' => 'totp',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        SecurityEvent::record($event, $user, 'totp');

        return true;
    }

    /** @return string[] plaintext codes — shown to the user exactly once, never stored or logged in plain form */
    public function generateBackupCodes(User $user): array
    {
        $user->backupCodes()->delete();

        $count = config('twofactor.backup_codes_count');
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $plain = Str::upper(Str::random(4).'-'.Str::random(4));
            $codes[] = $plain;

            TwoFactorBackupCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($plain),
            ]);
        }

        SecurityEvent::record('two_factor_backup_codes_regenerated', $user);

        return $codes;
    }

    public function verifyBackupCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));

        $match = $user->backupCodes()->whereNull('used_at')->get()
            ->first(fn (TwoFactorBackupCode $backup) => Hash::check($code, $backup->code_hash));

        if (! $match) {
            return false;
        }

        $match->update(['used_at' => now()]);

        return true;
    }

    /** True once fewer than 3 unused backup codes remain, prompting a regenerate nudge in Account Security. */
    public function backupCodesRunningLow(User $user): bool
    {
        return $user->backupCodes()->whereNull('used_at')->count() < 3;
    }

    public function sendEmailCode(User $user): void
    {
        $plain = (string) random_int(100000, 999999);

        $user->emailCodes()->create([
            'code_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(config('twofactor.email_code_ttl_minutes')),
        ]);

        // Sent synchronously, not queued: the user is actively waiting on this
        // screen for the code, and there's no queue worker running in this
        // dev environment to ever process a queued job.
        Mail::to($user->email)->send(new TwoFactorCodeMail($plain));
    }

    public function canResendEmailCode(User $user): bool
    {
        $latest = $user->emailCodes()->latest()->first();

        if (! $latest) {
            return true;
        }

        return $latest->created_at->addSeconds(config('twofactor.email_resend_cooldown_seconds'))->isPast();
    }

    public function secondsUntilEmailResend(User $user): int
    {
        $latest = $user->emailCodes()->latest()->first();

        if (! $latest) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($latest->created_at->addSeconds(config('twofactor.email_resend_cooldown_seconds')), false));
    }

    public function verifyEmailCode(User $user, string $code): bool
    {
        $match = $user->emailCodes()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get()
            ->first(fn (TwoFactorEmailCode $entry) => Hash::check(trim($code), $entry->code_hash));

        if (! $match) {
            return false;
        }

        $match->update(['consumed_at' => now()]);

        return true;
    }

    /** Switching to email needs no secret; switching to TOTP is handled by confirmTotpEnrollment() instead. */
    public function switchToEmail(User $user): void
    {
        $event = $user->hasTwoFactorEnrolled() ? 'two_factor_method_changed' : 'two_factor_enrolled';

        $user->forceFill([
            'two_factor_method' => 'email',
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $user->backupCodes()->delete();

        SecurityEvent::record($event, $user, 'email');
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->backupCodes()->delete();
        $this->revokeAllTrustedDevices($user);

        SecurityEvent::record('two_factor_disenrolled', $user);
    }

    /** Issues a new trusted device, persists only its hash (mirrors Laravel's own remember-token pattern), and returns the raw token for the caller to set as a cookie. */
    public function issueTrustedDevice(User $user, ?string $userAgent, ?string $ip): string
    {
        $raw = Str::random(64);

        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'trusted_at' => now(),
            'expires_at' => now()->addDays($this->trustedDeviceDaysFor($user)),
        ]);

        SecurityEvent::record('device_trusted', $user);

        return $raw;
    }

    public function isTrustedDeviceCookieValid(User $user, ?string $rawToken): bool
    {
        if (! $rawToken || ! $this->trustedDeviceAllowedFor($user)) {
            return false;
        }

        $device = $user->trustedDevices()
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return false;
        }

        if (! $device->last_used_at || $device->last_used_at->lt(now()->subHour())) {
            $device->update(['last_used_at' => now()]);
        }

        return true;
    }

    public function revokeTrustedDevice(User $user, int $id): void
    {
        if ($user->trustedDevices()->where('id', $id)->delete()) {
            SecurityEvent::record('device_revoked', $user);
        }
    }

    public function revokeAllTrustedDevices(User $user): void
    {
        if ($user->trustedDevices()->count() > 0) {
            $user->trustedDevices()->delete();
            SecurityEvent::record('device_revoked', $user, metadata: ['all' => true]);
        }
    }
}
