<?php

use App\Models\Setting;
use App\Rules\PasswordPolicy;
use App\Services\AccountLockoutService;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Self-service password change (spec Section A1), reused for both the
 * Account Security panel and the forced "enforce on login" flow
 * (App\Http\Middleware\EnsurePasswordPolicyMet) — $forced controls only what
 * happens after a successful save (redirect on vs. stay and flash).
 */
new class extends Component
{
    public bool $forced = false;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function save(AccountLockoutService $lockout): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'confirmed', new PasswordPolicy],
        ], [], ['newPassword' => 'new password']);

        if (! Hash::check($this->currentPassword, auth()->user()->password)) {
            $this->addError('currentPassword', 'That\'s not your current password.');

            return;
        }

        auth()->user()->setOwnPassword($this->newPassword);
        $lockout->clear(auth()->user());

        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);

        if ($this->forced) {
            $this->redirectRoute('home', navigate: true);

            return;
        }

        session()->flash('status', 'Password updated.');
    }

    public function with(): array
    {
        $settings = Setting::current();
        $requirements = array_filter([
            "at least {$settings->password_min_length} characters",
            $settings->password_max_length ? "no more than {$settings->password_max_length} characters" : null,
            $settings->password_require_uppercase ? 'an uppercase letter' : null,
            $settings->password_require_lowercase ? 'a lowercase letter' : null,
            $settings->password_require_number ? 'a number' : null,
            $settings->password_require_special ? 'a special character' : null,
            ! $settings->password_allow_spaces ? 'no spaces' : null,
            $settings->password_min_zxcvbn_score ? 'enough real-world strength (a common or easily-guessed password is rejected even if it satisfies every rule above)' : null,
        ]);

        return ['requirements' => $requirements];
    }
};
?>

<div>
    @if(session('status'))
        <div class="pill pill-success" style="margin-bottom:16px;padding:10px 14px;">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" style="display:flex;flex-direction:column;gap:14px;max-width:360px;">
        <div>
            <label for="current-password" style="font-size:var(--fs-sm);font-weight:600;display:block;margin-bottom:6px;">Current password</label>
            <input id="current-password" type="password" wire:model="currentPassword" autocomplete="current-password"
                   style="width:100%;padding:9px 12px;border:1px solid var(--color-border);border-radius:8px;">
            @error('currentPassword') <div class="hint" style="color:var(--color-danger);margin-top:4px;">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="new-password" style="font-size:var(--fs-sm);font-weight:600;display:block;margin-bottom:6px;">New password</label>
            <input id="new-password" type="password" wire:model="newPassword" autocomplete="new-password"
                   style="width:100%;padding:9px 12px;border:1px solid var(--color-border);border-radius:8px;">
            @error('newPassword') <div class="hint" style="color:var(--color-danger);margin-top:4px;">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="new-password-confirmation" style="font-size:var(--fs-sm);font-weight:600;display:block;margin-bottom:6px;">Confirm new password</label>
            <input id="new-password-confirmation" type="password" wire:model="newPassword_confirmation" autocomplete="new-password"
                   style="width:100%;padding:9px 12px;border:1px solid var(--color-border);border-radius:8px;">
        </div>

        <div class="hint">Must have {{ implode(', ', $requirements) }}.</div>

        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-start;">Update password</button>
    </form>
</div>
