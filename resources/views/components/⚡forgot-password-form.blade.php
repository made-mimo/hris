<?php

use App\Mail\PasswordResetCodeMail;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Traits\ThrottlesAttempts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * Spec Section A1's self-service password reset via emailed, single-use,
 * time-expiring codes — mirrors the 2FA email-OTP pattern already
 * established in this codebase. Reuses Laravel's own `password_reset_tokens`
 * table (already migrated by the framework skeleton), storing a hashed
 * 6-digit code in `token` instead of the framework's default long token.
 */
new class extends Component
{
    use ThrottlesAttempts;

    public string $step = 'request'; // request | reset

    public string $email = '';

    public string $code = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function sendCode(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        // Hit the limiter regardless of whether the address exists (checked
        // below) — letting only real accounts count against it would let an
        // attacker use the rate-limit response itself to find out which
        // emails are registered, defeating the "same UI either way" rule
        // right below.
        if ($this->tooManyAttempts('password-reset-request', $this->email, 3)) {
            $this->step = 'reset';

            return;
        }
        $this->hitRateLimit('password-reset-request', $this->email, 600);

        $user = User::where('email', $this->email)->first();

        // Never reveal whether the address exists — same UI either way.
        if ($user) {
            $plain = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $this->email],
                ['token' => Hash::make($plain), 'created_at' => now()]
            );

            Mail::to($this->email)->send(new PasswordResetCodeMail($plain));
        }

        $this->step = 'reset';
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'newPassword' => ['required', 'string', 'confirmed', new PasswordPolicy],
        ], [], ['newPassword' => 'new password']);

        if ($this->tooManyAttempts('password-reset-verify', $this->email, 5)) {
            $seconds = $this->rateLimitSecondsRemaining('password-reset-verify', $this->email);
            $this->addError('code', "Too many attempts. Try again in {$seconds} second".($seconds === 1 ? '' : 's').'.');

            return;
        }

        $record = DB::table('password_reset_tokens')->where('email', $this->email)->first();

        // Security fix: `now()->diffInMinutes($record->created_at)` on a
        // *past* timestamp returns a negative number in this Carbon version
        // (diffInMinutes is signed by chronological direction, not always
        // positive), so `> 30` never once evaluated true — this code never
        // actually expired. `addMinutes(30)->isPast()` has no such
        // direction-of-comparison ambiguity.
        if (! $record || ! $record->created_at || \Illuminate\Support\Carbon::parse($record->created_at)->addMinutes(30)->isPast() || ! Hash::check($this->code, $record->token)) {
            $this->hitRateLimit('password-reset-verify', $this->email, 300);
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        $this->clearRateLimit('password-reset-verify', $this->email);

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        $user->update([
            'password' => $this->newPassword,
            'password_policy_version' => Setting::current()->password_policy_version,
        ]);

        DB::table('password_reset_tokens')->where('email', $this->email)->delete();

        session()->flash('status', 'Password reset. Sign in with your new password.');
        $this->redirectRoute('login', navigate: true);
    }
};
?>

<div>
    @if($step === 'request')
        <h1 style="font-size:28px;">Reset your password</h1>
        <p class="text-muted" style="margin:8px 0 24px;font-size:14px;line-height:1.55;">Enter your work email and we'll send you a code to reset your password.</p>

        <form wire:submit="sendCode">
            <div class="field">
                <label for="email">Work email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" placeholder="you@systemsintelligenz.com">
                @error('email') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;margin-top:8px;">Send reset code</button>
        </form>
    @else
        <h1 style="font-size:28px;">Check your email</h1>
        <p class="text-muted" style="margin:8px 0 24px;font-size:14px;line-height:1.55;">If an account exists for {{ $email }}, a 6-digit code was sent. It expires in 30 minutes.</p>

        <form wire:submit="resetPassword">
            <div class="field">
                <label for="code">6-digit code</label>
                <input id="code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                       class="otp-input" style="width:100%;letter-spacing:0.4em;font-size:22px;">
                @error('code') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="new-password">New password</label>
                <input id="new-password" type="password" wire:model="newPassword" autocomplete="new-password">
                @error('newPassword') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="new-password-confirmation">Confirm new password</label>
                <input id="new-password-confirmation" type="password" wire:model="newPassword_confirmation" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:46px;margin-top:8px;">Reset password</button>
        </form>
    @endif

    <a href="{{ route('login') }}" wire:navigate class="auth-back-link" style="display:block;margin-top:18px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
        Back to sign in
    </a>
</div>
