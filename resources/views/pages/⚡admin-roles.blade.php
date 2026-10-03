<?php

use App\Models\Role;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        return [
            'roles' => Role::withCount(['users', 'screenPermissions', 'dataGroupPermissions'])
                ->orderByDesc('is_system_role')->orderBy('name')->get(),
        ];
    }
};
?>

<x-layouts.app title="Roles &amp; Permissions">
    <div class="page-header">
        <div>
            <h1>Roles &amp; Permissions</h1>
            <p class="text-muted">The role-builder (spec Section A2): every role here is a row plus a permission matrix — nothing is hard-coded.</p>
        </div>
    </div>

    <div class="grid grid-3" style="align-items:start;">
        <section class="card col-span-2" style="padding:0;overflow:hidden;">
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 90px 90px 70px 90px;gap:12px;padding:12px 18px;border-bottom:1px solid var(--color-border);font-size:var(--fs-2xs);font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:var(--color-text-faint);">
                <span>Role</span><span>Type</span><span>Screens</span><span>Users</span><span></span>
            </div>
            @foreach($roles as $role)
                <div style="display:grid;grid-template-columns:minmax(0,1fr) 90px 90px 70px 90px;gap:12px;align-items:center;padding:13px 18px;border-bottom:1px solid var(--color-border);">
                    <span>
                        <span style="display:block;font-size:var(--fs-base);font-weight:600;">{{ $role->name }}{{ $role->is_situational ? ' (situational)' : '' }}</span>
                        <span class="text-muted" style="font-size:var(--fs-xs);">{{ $role->description }}</span>
                    </span>
                    <span>
                        <span class="pill {{ $role->is_system_role ? 'pill-neutral' : 'pill-success' }}">{{ $role->is_system_role ? 'System' : 'Custom' }}</span>
                    </span>
                    <span class="font-mono text-muted" style="font-size:var(--fs-sm);">{{ $role->screen_permissions_count }}</span>
                    <span class="font-mono text-muted" style="font-size:var(--fs-sm);">{{ $role->users_count }}</span>
                    <a href="{{ route('admin.roles.edit', $role) }}" wire:navigate class="btn btn-outline btn-sm" style="justify-self:end;">{{ $role->is_system_role ? 'View' : 'Manage' }}</a>
                </div>
            @endforeach
        </section>

        <livewire:role-create-form />
    </div>
</x-layouts.app>
