<?php

namespace App\Http\Controllers\Api;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\AccountLockoutService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Token issuance for the REST API (spec Section 3.2/F6 — the mobile/API
 * surface). A Sanctum personal-access token, not a session cookie — the
 * app's stand-in for spec's "general-purpose OAuth2 server" (Section A3).
 *
 * 2FA is enforced here the same way ⚡two-factor-verify.blade.php enforces
 * it for the web session, adapted to a stateless API: login() never hands
 * out a full-access token to a 2FA-required user who hasn't verified this
 * session. Instead it issues a short-lived, single-purpose Sanctum token
 * (ability `2fa:verify` only, 5-minute expiry) that verifyTwoFactor() alone
 * accepts — every other endpoint requires the `*` ability (see routes/api.php
 * and the `abilities` middleware registered in bootstrap/app.php), so a
 * pending token genuinely cannot reach anything else in the meantime.
 * Trusted-device skip works the same as the web flow, just carried as a
 * request field/response field instead of a cookie (an API client has no
 * cookie jar to rely on).
 */
class AuthController extends ApiController
{
    /**
     * A precomputed bcrypt hash with no corresponding real password — burned
     * against an unknown email so this endpoint takes roughly as long either
     * way. Auth::attempt() on the web guard gets this for free from
     * Laravel's own Timebox; Auth::once() below does not, since it skips the
     * session guard's attempt() wrapper entirely.
     */
    private const DUMMY_HASH = '$2y$12$LfKPN.CmH1bdYlDjX2AwmufvPFkaWV32C38EdwOz8eWhb2pnJ9K1u';

    public function login(Request $request, TwoFactorService $twoFactor, AccountLockoutService $lockout)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_token' => ['nullable', 'string'],
        ]);

        $target = User::where('email', $credentials['email'])->first();

        if ($target && $lockout->isLocked($target)) {
            SecurityEvent::record('login_failed', $target, metadata: ['reason' => 'locked', 'channel' => 'api']);

            return $this->failure(423, "This account is locked for {$lockout->minutesRemaining($target)} more minute".($lockout->minutesRemaining($target) === 1 ? '' : 's').'.');
        }

        if (! $target) {
            Hash::check($credentials['password'], self::DUMMY_HASH);
        }

        if (! Auth::once(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            if ($target) {
                $lockout->recordFailure($target);
            }

            SecurityEvent::record('login_failed', $target, metadata: ['email' => $credentials['email'], 'channel' => 'api']);

            return $this->failure(401, 'Those credentials don\'t match our records.');
        }

        $user = Auth::user();
        $lockout->clear($user);
        SecurityEvent::record('login_succeeded', $user, metadata: ['channel' => 'api']);

        $deviceTrusted = $twoFactor->isTrustedDeviceCookieValid($user, $credentials['device_token'] ?? null);

        if ($twoFactor->isRequiredFor($user) && ! $deviceTrusted) {
            if (! $user->hasTwoFactorEnrolled() || $twoFactor->needsReEnrollment($user)) {
                return $this->failure(403, 'Two-factor authentication must be enrolled via the web app before API access is available.');
            }

            if ($user->two_factor_method === 'email') {
                $twoFactor->sendEmailCode($user);
            }

            $pending = $user->createToken('2fa-pending', ['2fa:verify'], now()->addMinutes(5));

            return $this->success([
                'requires_2fa' => true,
                'method' => $user->two_factor_method,
                'pending_token' => $pending->plainTextToken,
                'expires_in' => 300,
            ]);
        }

        $token = $user->createToken($request->userAgent() ?? 'api');

        return $this->success([
            'token' => $token->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], status: 201);
    }

    /** Spec A1's "enter your code" step, using the same pending token login() just issued — see this class's own doc comment. */
    public function verifyTwoFactor(Request $request, TwoFactorService $twoFactor)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'use_backup_code' => ['boolean'],
            'trust_device' => ['boolean'],
        ]);

        $user = $request->user();
        $useBackupCode = $data['use_backup_code'] ?? false;

        if ($twoFactor->tooManyVerifyAttempts($user)) {
            $minutes = (int) ceil($twoFactor->verifyAttemptsSecondsRemaining($user) / 60);

            return $this->failure(429, "Too many attempts. This account's two-factor verification is paused for {$minutes} more minute".($minutes === 1 ? '' : 's').'.');
        }

        $ok = match (true) {
            $useBackupCode => $twoFactor->verifyBackupCode($user, $data['code']),
            $user->two_factor_method === 'totp' => $twoFactor->verifyTotp($user, $data['code']),
            $user->two_factor_method === 'email' => $twoFactor->verifyEmailCode($user, $data['code']),
            default => false,
        };

        if (! $ok) {
            $twoFactor->recordVerifyFailure($user);
            SecurityEvent::record('two_factor_verify_failed', $user, $useBackupCode ? 'backup_code' : $user->two_factor_method, ['channel' => 'api']);

            return $this->failure(422, 'That code is invalid or has expired.');
        }

        $twoFactor->clearVerifyAttempts($user);
        SecurityEvent::record('two_factor_verified', $user, $useBackupCode ? 'backup_code' : $user->two_factor_method, ['channel' => 'api']);

        // The pending token's only job was to get here — it's replaced by a
        // full-access one below, never reused or upgraded in place.
        $request->user()->currentAccessToken()->delete();

        $deviceToken = null;
        if (($data['trust_device'] ?? false) && $twoFactor->trustedDeviceAllowedFor($user)) {
            $deviceToken = $twoFactor->issueTrustedDevice($user, $request->userAgent(), $request->ip());
        }

        $token = $user->createToken($request->userAgent() ?? 'api');

        return $this->success([
            'token' => $token->plainTextToken,
            'device_token' => $deviceToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], status: 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(['revoked' => true]);
    }
}
