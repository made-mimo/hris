<?php

namespace App\Services;

use App\Models\Role;
use App\Models\RoleDataGroupPermission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The three-tier RBAC engine (spec Section 3.2/A2). This is the single place
 * that answers "what can this user do right now" — routes, middleware, and
 * Blade views all call through here rather than switching on a role string,
 * so a newly created custom role (spec's role-builder) is enforced
 * identically to a system role the moment its matrix is saved.
 *
 * A user's *effective* roles are computed at request time, not stored: their
 * one assigned base role, plus the situational Supervisor role layered on
 * top whenever the reporting-line graph actually makes them one (spec A2:
 * "Supervisor status is computed, not assigned").
 */
class PermissionService
{
    /** @return Collection<int, Role> */
    public function effectiveRoles(User $user): Collection
    {
        $roles = collect([$user->role])->filter();

        $isSupervisor = $user->employee && $user->employee->subordinates()->exists();

        if ($isSupervisor) {
            $supervisorRole = Role::where('slug', 'supervisor')->first();
            if ($supervisorRole) {
                $roles->push($supervisorRole);
            }
        }

        return $roles->unique('id')->values();
    }

    public function canViewScreen(User $user, string $screenKey): bool
    {
        return $this->effectiveRoles($user)
            ->contains(fn (Role $role) => $role->screens->contains('key', $screenKey));
    }

    /** @return Collection<int, \App\Models\Screen> every screen visible to this user, nav-ordered */
    public function visibleScreens(User $user): Collection
    {
        return $this->effectiveRoles($user)
            ->flatMap(fn (Role $role) => $role->screens)
            ->unique('id')
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * The strongest grant this user holds on a data group, taking the
     * highest-ranked scope and level independently across every effective
     * role — e.g. a Supervisor who is also ESS gets "self_subordinates" on
     * Leave Requests (from Supervisor) even though their base ESS role only
     * grants "self".
     */
    public function grantFor(User $user, string $dataGroupKey): array
    {
        $permissions = $this->effectiveRoles($user)
            ->flatMap(fn (Role $role) => $role->dataGroupPermissions()->whereHas(
                'dataGroup', fn ($q) => $q->where('key', $dataGroupKey)
            )->get());

        if ($permissions->isEmpty()) {
            return ['scope' => 'none', 'level' => 'none'];
        }

        $bestScope = $permissions->sortByDesc(fn (RoleDataGroupPermission $p) => $p->scopeRank())->first()->scope;
        $bestLevel = $permissions->sortByDesc(fn (RoleDataGroupPermission $p) => $p->levelRank())->first()->level;

        return ['scope' => $bestScope, 'level' => $bestLevel];
    }

    public function scopeFor(User $user, string $dataGroupKey): string
    {
        return $this->grantFor($user, $dataGroupKey)['scope'];
    }

    public function canOnDataGroup(User $user, string $dataGroupKey, string $minLevel = 'view'): bool
    {
        $levels = RoleDataGroupPermission::LEVELS;
        $grant = $this->grantFor($user, $dataGroupKey);

        return array_search($grant['level'], $levels, true) >= array_search($minLevel, $levels, true);
    }
}
