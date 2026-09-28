<?php

namespace App\Traits;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Spec's Non-Functional Requirements: "rate limiting on authentication and
 * password-reset endpoints." Those flows here are Livewire actions, not
 * plain HTTP routes with their own path a `throttle:` route-middleware
 * could gate — every Livewire action call actually hits the framework's
 * shared update endpoint — so throttling has to happen inside the action
 * itself, the same way Laravel's own (controller-oriented) ThrottlesLogins
 * trait does. Keyed by identifier (usually the submitted email) + IP, same
 * convention ThrottlesLogins itself uses.
 */
trait ThrottlesAttempts
{
    protected function tooManyAttempts(string $scope, string $identifier, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($scope, $identifier), $maxAttempts);
    }

    protected function rateLimitSecondsRemaining(string $scope, string $identifier): int
    {
        return RateLimiter::availableIn($this->throttleKey($scope, $identifier));
    }

    protected function hitRateLimit(string $scope, string $identifier, int $decaySeconds): void
    {
        RateLimiter::hit($this->throttleKey($scope, $identifier), $decaySeconds);
    }

    protected function clearRateLimit(string $scope, string $identifier): void
    {
        RateLimiter::clear($this->throttleKey($scope, $identifier));
    }

    private function throttleKey(string $scope, string $identifier): string
    {
        return $scope.'|'.Str::transliterate(Str::lower($identifier)).'|'.request()->ip();
    }
}
