<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec Section A1's "enforce on login": a password policy tightened after an
 * account was created must be satisfied at next login. Setting::password_policy_version
 * bumps whenever Admin actually changes a policy field; a user whose own
 * `password_policy_version` is behind that is routed through a forced
 * password change before reaching anything else.
 *
 * Exempts the current session when it authenticated via SSO (see
 * SsoController) — that forced change asks for the account's *current*
 * password, which an SSO-only user may never have set or known, turning an
 * unrelated policy bump into a lockout for them.
 */
class EnsurePasswordPolicyMet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user
            || $user->password_policy_version >= Setting::current()->password_policy_version
            || $request->session()->get('sso_authenticated')) {
            return $next($request);
        }

        return redirect()->route('account.update-password');
    }
}
