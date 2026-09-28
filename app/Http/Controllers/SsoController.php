<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * Spec Section A3's SSO, backed by Google Workspace and Microsoft 365 via
 * OAuth 2.0/OIDC (Laravel Socialite). Deliberately does NOT auto-provision
 * accounts: this app's model is "accounts are created by HR during
 * onboarding" (see the login form's own copy), so a successful IdP sign-in
 * that doesn't match an existing User by email is rejected the same way a
 * wrong password is, rather than silently creating one. Credentials live in
 * the admin-editable Setting row (encrypted at rest), not .env — the same
 * pattern this app already uses for SMTP — so enabling/disabling a provider
 * or rotating a client secret takes effect immediately, no redeploy needed.
 */
class SsoController extends Controller
{
    private const PROVIDERS = ['google', 'microsoft'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $settings = Setting::current();
        abort_unless($this->isReady($provider, $settings), 404);

        $this->configureDriver($provider, $settings);

        $driver = Socialite::driver($provider);

        if ($provider === 'google' && $settings->sso_google_domain) {
            // Restricts Google's own account chooser to the Workspace
            // domain, so a personal Gmail account never even shows up as an
            // option — a UX nicety on top of the email match below, which
            // is the actual access control.
            $driver = $driver->with(['hd' => $settings->sso_google_domain]);
        }

        return $driver->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $settings = Setting::current();
        abort_unless($this->isReady($provider, $settings), 404);

        $this->configureDriver($provider, $settings);

        try {
            $ssoUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->with('ssoError', 'Sign-in with '.ucfirst($provider).' was cancelled or failed. Please try again, or use your password.');
        }

        $email = strtolower((string) $ssoUser->getEmail());

        if ($email === '') {
            return redirect()->route('login')->with('ssoError', ucfirst($provider).' did not share an email address for that account.');
        }

        if ($provider === 'google' && $settings->sso_google_domain && ! str_ends_with($email, '@'.strtolower($settings->sso_google_domain))) {
            SecurityEvent::record('sso_login_rejected', metadata: ['provider' => $provider, 'email' => $email, 'reason' => 'domain_mismatch']);

            return redirect()->route('login')->with('ssoError', 'That Google account is outside the approved Workspace domain.');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            SecurityEvent::record('sso_login_rejected', metadata: ['provider' => $provider, 'email' => $email, 'reason' => 'no_matching_account']);

            return redirect()->route('login')->with('ssoError', "No HRIS account matches {$email}. Accounts are created by HR during onboarding.");
        }

        Auth::login($user);
        request()->session()->regenerate();
        // The identity provider is itself the strong-auth factor (Google/
        // Microsoft sign-in already enforces its own MFA policy), so a
        // successful SSO round-trip satisfies this app's own 2FA gate the
        // same way a trusted-device cookie does.
        request()->session()->put('two_factor_verified', true);
        SecurityEvent::record('sso_login_succeeded', $user, $provider);

        return redirect()->route('home');
    }

    private function isReady(string $provider, Setting $settings): bool
    {
        return match ($provider) {
            'google' => $settings->googleSsoReady(),
            'microsoft' => $settings->microsoftSsoReady(),
        };
    }

    private function configureDriver(string $provider, Setting $settings): void
    {
        $redirect = route('sso.callback', $provider);

        if ($provider === 'google') {
            config([
                'services.google.client_id' => $settings->sso_google_client_id,
                'services.google.client_secret' => $settings->sso_google_client_secret,
                'services.google.redirect' => $redirect,
            ]);

            return;
        }

        config([
            'services.microsoft.client_id' => $settings->sso_microsoft_client_id,
            'services.microsoft.client_secret' => $settings->sso_microsoft_client_secret,
            'services.microsoft.redirect' => $redirect,
            // "organizations" accepts any Microsoft Entra work/school
            // tenant but excludes personal Microsoft accounts — the right
            // default for O365 business SSO. An admin who wants to lock
            // this to one specific company tenant enters its Tenant ID.
            'services.microsoft.tenant' => $settings->sso_microsoft_tenant_id ?: 'organizations',
        ]);
    }
}
