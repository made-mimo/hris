<?php

use App\Models\Role;
use Livewire\Component;

new class extends Component
{
    public Role $role;

    public function mount(Role $role): void
    {
        $this->role = $role;
    }
};
?>

<x-layouts.app title="{{ $role->name }}">
    <div class="page-header">
        <div>
            <div class="text-muted" style="font-size:13px;margin-bottom:6px;">
                <a href="{{ route('admin.roles') }}" wire:navigate class="text-muted">Roles &amp; Permissions</a> <span>/</span> <span style="color:var(--color-text);font-weight:600;">{{ $role->name }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <h1>{{ $role->name }}</h1>
                <span class="pill {{ $role->is_system_role ? 'pill-neutral' : 'pill-success' }}">{{ $role->is_system_role ? 'System — read only' : 'Custom' }}</span>
            </div>
            <p class="text-muted" style="margin-top:4px;">{{ $role->description }}</p>
        </div>
    </div>

    <livewire:role-matrix-editor :role="$role" :key="'matrix-'.$role->id" />

    <div style="margin-top:24px;">
        <livewire:role-two-factor-policy :role="$role" :key="'2fa-policy-'.$role->id" />
    </div>
</x-layouts.app>
