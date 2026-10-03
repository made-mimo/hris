<?php

use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountLockoutService;
use App\Services\TwoFactorService;
use App\Traits\ThrottlesAttempts;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The interactive body of /login. Kept as a child component per the
 * "inert page + child Livewire component" rule (PLAN.md 4.3) — a full-page
 * SFC re-rendering itself in place (e.g. via addError() on a failed login,
 * not just wire:click) hits the same Livewire-4 full-document-morph defect;
 * this was previously undiscovered here because only successful logins
 * (which redirect instead of re-rendering in place) had ever been tested.
 */
new class extends Component
{
    use ThrottlesAttempts;

    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    /** @var string[] */
    public array $ssoProviders = [];

    public function mount(): void
    {
        $settings = Setting::current();

        if ($settings->googleSsoReady()) {
            $this->ssoProviders[] = 'google';
        }

        if ($settings->microsoftSsoReady()) {
            $this->ssoProviders[] = 'microsoft';
        }
    }

    public function login(TwoFactorService $twoFactor, AccountLockoutService $lockout): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($this->tooManyAttempts('login', $credentials['email'], 5)) {
            $seconds = $this->rateLimitSecondsRemaining('login', $credentials['email']);
            $this->addError('email', "Too many attempts. Try again in {$seconds} second".($seconds === 1 ? '' : 's').'.');

            return;
        }

        // Per-account lockout (PIM/HRIS alignment §3C item 1) — separate from
        // the per-(email, IP) throttle above, so rotating source IPs can't
        // defeat it. Looked up before Auth::attempt() since a locked account
        // must not even have its password checked.
        $target = User::where('email', $credentials['email'])->first();

        if ($target && $lockout->isLocked($target)) {
            SecurityEvent::record('login_failed', $target, metadata: ['reason' => 'locked']);
            $this->addError('email', "Too many failed attempts. This account is locked for {$lockout->minutesRemaining($target)} more minute".($lockout->minutesRemaining($target) === 1 ? '' : 's').'.');

            return;
        }

        // A role that must re-verify 2FA at every sign-in (no trusted
        // devices) shouldn't get a remember-me cookie either — that cookie
        // would silently re-establish a session on a later visit the same
        // way a trusted-device cookie does, defeating the point of denying
        // it one (PIM/HRIS alignment §3C item 9).
        $remember = $this->remember && (! $target || $twoFactor->trustedDeviceAllowedFor($target));

        if (! Auth::attempt($credentials, $remember)) {
            $this->hitRateLimit('login', $credentials['email'], 60);

            if ($target) {
                $lockout->recordFailure($target);
            }

            SecurityEvent::record('login_failed', $target, metadata: ['email' => $this->email]);
            $this->addError('email', 'Those credentials don\'t match our records.');

            return;
        }

        $this->clearRateLimit('login', $credentials['email']);
        request()->session()->regenerate();

        $user = Auth::user();
        $lockout->clear($user);
        SecurityEvent::record('login_succeeded', $user);
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

<div>
    <form wire:submit="login">
        <div class="auth-eyebrow">HR INFORMATION SYSTEM</div>
        <h1 style="font-size:30px;">Welcome back</h1>
        <p class="text-muted" style="margin:8px 0 30px;font-size:var(--fs-base);">Sign in to apply for leave, submit claims and manage your team.</p>

        @if(session('status'))
            <div class="pill pill-success" style="margin-bottom:20px;padding:10px 14px;">{{ session('status') }}</div>
        @endif
        @if(session('ssoError'))
            <div class="pill pill-danger" style="margin-bottom:20px;padding:10px 14px;">{{ session('ssoError') }}</div>
        @endif

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
            <label style="display:flex;align-items:center;gap:8px;font-size:var(--fs-sm);color:var(--color-text-muted);cursor:pointer;">
                <input type="checkbox" wire:model="remember" style="width:16px;height:16px;accent-color:var(--color-primary);">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" wire:navigate style="font-size:var(--fs-sm);font-weight:600;">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;">Sign in</button>

        @if(count($ssoProviders))
            <div style="display:flex;align-items:center;gap:12px;margin:22px 0;">
                <div style="flex:1;height:1px;background:var(--color-border);"></div>
                <div class="text-muted" style="font-size:var(--fs-xs);">or</div>
                <div style="flex:1;height:1px;background:var(--color-border);"></div>
            </div>

            @if(in_array('google', $ssoProviders))
                <a href="{{ route('sso.redirect', 'google') }}" class="btn btn-outline" style="width:100%;justify-content:center;height:46px;text-decoration:none;margin-bottom:10px;">
                    <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.1 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.5 16 18.9 13 24 13c3.1 0 5.9 1.1 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.5 0 10.5-2.1 14.3-5.6l-6.6-5.6C29.6 34.7 27 35.7 24 35.7c-5.2 0-9.6-3.3-11.3-7.9l-6.6 5.1C9.6 39.6 16.3 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.2 4.3-4.1 5.7l6.6 5.6C39.9 37 44 31 44 24c0-1.3-.1-2.7-.4-3.5z"/></svg>
                    Continue with Google
                </a>
            @endif

            @if(in_array('microsoft', $ssoProviders))
                <a href="{{ route('sso.redirect', 'microsoft') }}" class="btn btn-outline" style="width:100%;justify-content:center;height:46px;text-decoration:none;">
                    <svg width="18" height="18" viewBox="0 0 23 23"><rect x="1" y="1" width="10" height="10" fill="#f25022"/><rect x="12" y="1" width="10" height="10" fill="#7fba00"/><rect x="1" y="12" width="10" height="10" fill="#00a4ef"/><rect x="12" y="12" width="10" height="10" fill="#ffb900"/></svg>
                    Continue with Microsoft
                </a>
            @endif
        @endif

        <p class="text-muted" style="margin:22px 0 0;font-size:var(--fs-xs);text-align:center;line-height:1.55;">
            Accounts are created by HR during onboarding.<br>Can't sign in? Contact HR &amp; Admin.
        </p>
    </form>
</div>
