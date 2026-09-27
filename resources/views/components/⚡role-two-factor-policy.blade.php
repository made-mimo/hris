<?php

use App\Models\Role;
use App\Models\SecurityEvent;
use Livewire\Component;

/**
 * Spec Section A1's "Admin policy control": per-role refinement layered on
 * top of the project-wide Setting::two_factor_enabled switch — which methods
 * a role may use, whether it can skip via a trusted device, and for how
 * long. Unlike the screen/data-group permission matrix, this is editable
 * for every role including system roles — the spec's own example ("require
 * TOTP specifically for elevated roles such as Admin/HR Admin") only makes
 * sense applied to system roles.
 */
new class extends Component
{
    public Role $role;

    public bool $required = true;

    public bool $allowTotp = true;

    public bool $allowEmail = true;

    public bool $trustedDeviceAllowed = true;

    public ?int $trustedDeviceDays = null;

    public function mount(Role $role): void
    {
        $this->role = $role;
        $this->required = $role->two_factor_required;
        $methods = $role->twoFactorAllowedMethods();
        $this->allowTotp = in_array('totp', $methods, true);
        $this->allowEmail = in_array('email', $methods, true);
        $this->trustedDeviceAllowed = $role->two_factor_trusted_device_allowed;
        $this->trustedDeviceDays = $role->two_factor_trusted_device_days;
    }

    public function save(): void
    {
        $this->validate([
            'trustedDeviceDays' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if (! $this->allowTotp && ! $this->allowEmail) {
            $this->addError('allowTotp', 'At least one method must stay allowed.');

            return;
        }

        $this->role->update([
            'two_factor_required' => $this->required,
            'two_factor_allowed_methods' => array_values(array_filter([
                $this->allowTotp ? 'totp' : null,
                $this->allowEmail ? 'email' : null,
            ])),
            'two_factor_trusted_device_allowed' => $this->trustedDeviceAllowed,
            'two_factor_trusted_device_days' => $this->trustedDeviceDays,
        ]);

        // Spec A1: "2FA required by policy change" is a named 2FA lifecycle
        // event in its own right, on top of the generic field-diff that
        // Role's Auditable trait already records for this same update.
        SecurityEvent::record('two_factor_policy_changed', auth()->user(), metadata: ['role' => $this->role->slug]);

        session()->flash('status', 'Two-factor policy updated for this role.');
    }
};
?>

<section class="card">
    <div class="card-header"><h2>Two-factor policy</h2></div>

    @if(session('status'))
        <div class="pill pill-success" style="margin-bottom:16px;padding:10px 14px;">{{ session('status') }}</div>
    @endif

    @if($role->is_situational)
        <div class="hint">Situational roles (like Supervisor) are layered on top of a user's base role — 2FA policy is resolved from their base role, so this has no effect here.</div>
    @else
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:14px;max-width:480px;">
            <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid var(--color-border);border-radius:10px;background:var(--color-bg);cursor:pointer;">
                <input type="checkbox" wire:model="required" style="width:16px;height:16px;margin-top:2px;accent-color:var(--color-primary);">
                <span>
                    <span style="display:block;font-size:13.5px;font-weight:600;">Require 2FA for this role</span>
                    <span style="display:block;font-size:12.5px;color:var(--color-text-muted);">Only takes effect while the project-wide switch (Settings) is also on.</span>
                </span>
            </label>

            <div>
                <div style="font-size:13px;font-weight:600;margin-bottom:8px;">Allowed methods</div>
                <div style="display:flex;gap:16px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;cursor:pointer;">
                        <input type="checkbox" wire:model="allowTotp" style="width:16px;height:16px;accent-color:var(--color-primary);">
                        Authenticator app
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;cursor:pointer;">
                        <input type="checkbox" wire:model="allowEmail" style="width:16px;height:16px;accent-color:var(--color-primary);">
                        Email code
                    </label>
                </div>
                @error('allowTotp') <div class="hint" style="color:var(--color-danger);margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid var(--color-border);border-radius:10px;background:var(--color-bg);cursor:pointer;">
                <input type="checkbox" wire:model="trustedDeviceAllowed" style="width:16px;height:16px;margin-top:2px;accent-color:var(--color-primary);">
                <span>
                    <span style="display:block;font-size:13.5px;font-weight:600;">Allow trusted-device skip</span>
                    <span style="display:block;font-size:12.5px;color:var(--color-text-muted);">Off means this role verifies 2FA at every sign-in, no exceptions.</span>
                </span>
            </label>

            <div>
                <label for="td-days" style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">Trusted-device expiry override (days)</label>
                <input id="td-days" type="number" wire:model="trustedDeviceDays" min="1" max="365" placeholder="Use project default ({{ config('twofactor.trusted_device_days') }})"
                       style="width:100%;padding:9px 12px;border:1px solid var(--color-border);border-radius:8px;">
                @error('trustedDeviceDays') <div class="hint" style="color:var(--color-danger);margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-start;">Save policy</button>
        </form>
    @endif
</section>
