<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates access behind the 2FA step (spec Section A1). A trusted-device
 * cookie, once issued, skips this for its configured lifetime — the cookie
 * itself only carries a random opaque token, verified here against a
 * per-device hash in `trusted_devices` (never the user id directly). Admin
 * can switch 2FA off project-wide from Settings (Section A5, defaulted OFF
 * for this dev phase) or refine it per role (see Role's two_factor_* columns
 * and TwoFactorService::isRequiredFor()).
 */
class EnsureTwoFactorVerified
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user
            || ! $this->twoFactor->isRequiredFor($user)
            || $request->session()->get('two_factor_verified')
            || $this->twoFactor->isTrustedDeviceCookieValid($user, $request->cookie('trusted_device'))) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnrolled() || $this->twoFactor->needsReEnrollment($user)) {
            return redirect()->route('login.setup');
        }

        return redirect()->route('login.verify');
    }
}
