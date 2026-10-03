<?php

use App\Models\DataGroup;
use App\Models\Role;
use App\Models\RoleDataGroupPermission;
use App\Models\Screen;
use Livewire\Component;

new class extends Component
{
    public Role $role;

    /** @var array<int,bool> screen_id => can_view */
    public array $screenGrants = [];

    /** @var array<int,array{scope:string,level:string}> data_group_id => grant */
    public array $groupGrants = [];

    public function mount(Role $role): void
    {
        $this->role = $role->fresh(['screenPermissions', 'dataGroupPermissions']);

        foreach (Screen::orderBy('sort_order')->get() as $screen) {
            $this->screenGrants[$screen->id] = $this->role->screenPermissions
                ->firstWhere('screen_id', $screen->id)?->can_view ?? false;
        }

        foreach (DataGroup::orderBy('label')->get() as $group) {
            $existing = $this->role->dataGroupPermissions->firstWhere('data_group_id', $group->id);
            $this->groupGrants[$group->id] = [
                'scope' => $existing?->scope ?? 'none',
                'level' => $existing?->level ?? 'none',
            ];
        }
    }

    public function save(): void
    {
        if ($this->role->is_system_role) {
            abort(403, 'System roles cannot be edited — spec A2 guardrail.');
        }

        foreach ($this->screenGrants as $screenId => $canView) {
            $this->role->screenPermissions()->updateOrCreate(
                ['screen_id' => $screenId],
                ['can_view' => (bool) $canView]
            );
        }

        foreach ($this->groupGrants as $groupId => $grant) {
            $this->role->dataGroupPermissions()->updateOrCreate(
                ['data_group_id' => $groupId],
                ['scope' => $grant['scope'], 'level' => $grant['level']]
            );
        }

        session()->flash('status', 'Permission matrix saved.');
    }

    public function delete(): void
    {
        if ($this->role->is_system_role) {
            abort(403, 'System roles cannot be deleted — spec A2 guardrail.');
        }

        if ($this->role->users()->exists()) {
            $this->addError('delete', 'This role still has users assigned — reassign them first.');

            return;
        }

        $this->role->delete();
        $this->redirectRoute('admin.roles', navigate: true);
    }

    public function with(): array
    {
        return [
            'screens' => Screen::orderBy('sort_order')->get(),
            'groups' => DataGroup::orderBy('label')->get(),
            'scopes' => RoleDataGroupPermission::SCOPES,
            'levels' => RoleDataGroupPermission::LEVELS,
            'assignedCount' => $this->role->users()->count(),
        ];
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px;">
    @if(session('status'))
        <div class="pill pill-success" style="padding:10px 14px;">{{ session('status') }}</div>
    @endif
    @if($role->is_system_role)
        <div class="hint" style="padding:12px 14px;background:var(--color-bg);border-radius:8px;">
            This is one of the four system roles (plus Supervisor) — read-only, per spec A2: "to keep a known-good baseline always available." Clone it from the roles list to build a custom variant.
        </div>
    @endif

    <section class="card">
        <div class="card-header"><h2>Screens visible to this role</h2></div>
        <div class="grid grid-3">
            @foreach($screens as $screen)
                <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--color-border);border-radius:8px;{{ $role->is_system_role ? '' : 'cursor:pointer;' }}">
                    <input type="checkbox" wire:model="screenGrants.{{ $screen->id }}" @disabled($role->is_system_role) style="width:16px;height:16px;accent-color:var(--color-primary);">
                    <span style="font-size:var(--fs-sm);font-weight:500;">{{ $screen->label }}</span>
                </label>
            @endforeach
        </div>
    </section>

    <section class="card" style="padding:0;overflow:hidden;">
        <div class="card-header" style="padding:20px 20px 0;"><h2>Data-group permission matrix</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Data group</th><th style="width:220px;">Scope</th><th style="width:220px;">Level</th></tr></thead>
                <tbody>
                    @foreach($groups as $group)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $group->label }}</div>
                                @if($group->description)<div class="text-muted" style="font-size:var(--fs-xs);">{{ $group->description }}</div>@endif
                            </td>
                            <td>
                                <select wire:model="groupGrants.{{ $group->id }}.scope" @disabled($role->is_system_role)>
                                    @foreach($scopes as $s)<option value="{{ $s }}">{{ ucfirst(str_replace('_', ' + ', $s)) }}</option>@endforeach
                                </select>
                            </td>
                            <td>
                                <select wire:model="groupGrants.{{ $group->id }}.level" @disabled($role->is_system_role)>
                                    @foreach($levels as $l)<option value="{{ $l }}">{{ ucfirst(str_replace('_', ' + ', $l)) }}</option>@endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @unless($role->is_system_role)
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                @error('delete') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
                <button type="button" wire:click="delete" wire:confirm="Delete this custom role permanently?" class="btn btn-outline" style="color:var(--color-danger);border-color:var(--color-danger-light);">
                    Delete role{{ $assignedCount > 0 ? " ({$assignedCount} user".($assignedCount === 1 ? '' : 's')." assigned)" : '' }}
                </button>
            </div>
            <button type="button" wire:click="save" class="btn btn-primary">Save permission matrix</button>
        </div>
    @endunless
</div>
