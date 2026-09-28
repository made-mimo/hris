<?php

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\User;

/**
 * PIM/HRIS alignment §3C item 1 — per-account lockout, independent of the
 * per-(email, IP) rate limiting ThrottlesAttempts already does: 5 wrong
 * passwords locks the *account* for 15 minutes regardless of which IP the
 * attempts came from, so an attacker can't defeat the limit by rotating
 * source addresses. The web login form, the API login endpoint, and a
 * successful password reset/change all go through this one service.
 */
class AccountLockoutService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    public function isLocked(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    public function minutesRemaining(User $user): int
    {
        return $user->locked_until ? (int) max(1, now()->diffInMinutes($user->locked_until, false)) : 0;
    }

    /** Called after a wrong password for an account that exists. Locks it once the threshold is hit. */
    public function recordFailure(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;

        $data = ['failed_login_attempts' => $attempts];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $data['locked_until'] = now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $user->forceFill($data)->save();

        if ($attempts >= self::MAX_ATTEMPTS) {
            SecurityEvent::record('account_locked', $user, metadata: ['minutes' => self::LOCKOUT_MINUTES]);
        }
    }

    /** Called on a successful password check (sign-in) or a successful password reset/change — either proves account ownership. */
    public function clear(User $user): void
    {
        if ($user->failed_login_attempts > 0 || $user->locked_until !== null) {
            $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        }
    }
}
