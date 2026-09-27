<?php

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(TwoFactorService $twoFactor): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $this->remember)) {
            $this->addError('email', 'Those credentials don\'t match our records.');

            return;
        }

        request()->session()->regenerate();

        $user = Auth::user();
        $trusted = $twoFactor->isTrustedDeviceCookieValid($user, request()->cookie('trusted_device'));

        if ($trusted || ! $twoFactor->isRequiredFor($user)) {
            request()->session()->put('two_factor_verified', true);
            $this->redirectRoute('home', navigate: true);

            return;
        }

        if (! $user->hasTwoFactorEnrolled() || $twoFactor->needsReEnrollment($user)) {
            $this->redirectRoute('login.setup', navigate: true);

            return;
        }

        $this->redirectRoute('login.verify', navigate: true);
    }
};
?>

<x-layouts.guest
    title="Sign in"
    headline="Your people, your workday — in one place."
    subtext="Leave, claims, attendance, onboarding and approvals for the Systems Intelligenz team."
>
    <x-slot:illustration>
        <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#D9251E" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
            <circle cx="12" cy="7" r="3.4"></circle>
            <path d="M4.5 20c1.4-4.3 4-6.5 7.5-6.5s6.1 2.2 7.5 6.5"></path>
        </svg>
    </x-slot:illustration>

    <form wire:submit="login">
        <div class="auth-eyebrow">HR INFORMATION SYSTEM</div>
        <h1 style="font-size:30px;">Welcome back</h1>
        <p class="text-muted" style="margin:8px 0 30px;font-size:14px;">Sign in to apply for leave, submit claims and manage your team.</p>

        <div class="field">
            <label for="email">Work email or username</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" placeholder="you@systemsintelligenz.com">
            @error('email') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
        </div>

        <div class="field" x-data="{ show: false }">
            <label for="password">Password</label>
            <div style="position:relative;display:flex;align-items:center;">
                <input id="password" :type="show ? 'text' : 'password'" wire:model="password" autocomplete="current-password" placeholder="Enter your password" style="padding-right:48px;">
                <button type="button" @click="show = !show" aria-label="Show or hide password" class="icon-btn" style="position:absolute;right:4px;width:36px;height:36px;background:transparent;border:none;">
                    <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7z"></path><circle cx="12" cy="12" r="2.6"></circle></svg>
                    <svg x-show="show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7z"></path><circle cx="12" cy="12" r="2.6"></circle><line x1="4" y1="20" x2="20" y2="4"></line></svg>
                </button>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:26px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--color-text-muted);cursor:pointer;">
                <input type="checkbox" wire:model="remember" style="width:16px;height:16px;accent-color:var(--color-primary);">
                Remember me
            </label>
            <a href="#" style="font-size:13px;font-weight:600;">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;">Sign in</button>

        <div style="display:flex;align-items:center;gap:12px;margin:22px 0;">
            <div style="flex:1;height:1px;background:var(--color-border);"></div>
            <div class="text-muted" style="font-size:12px;">or</div>
            <div style="flex:1;height:1px;background:var(--color-border);"></div>
        </div>

        <button type="button" class="btn btn-outline" style="width:100%;justify-content:center;height:46px;" onclick="alert('SSO is not configured in this local prototype.')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
            Sign in with company SSO
        </button>

        <p class="text-muted" style="margin:22px 0 0;font-size:12.5px;text-align:center;line-height:1.55;">
            Accounts are created by HR during onboarding.<br>Can't sign in? Contact HR &amp; Admin.
        </p>

        <div class="hint" style="margin-top:24px;padding:12px 14px;background:var(--color-bg);border-radius:8px;">
            Demo logins (password <code>password</code>): <code>admin@systemsintelligenz.com</code> (Admin) · <code>hr@systemsintelligenz.com</code> (HR &amp; Admin) · <code>emeka@systemsintelligenz.com</code> (Line Manager) · <code>adaeze@systemsintelligenz.com</code> (ESS + Line Manager)
        </div>
    </form>
</x-layouts.guest>
