<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'is_system_role', 'is_situational',
        'two_factor_required', 'two_factor_allowed_methods',
        'two_factor_trusted_device_allowed', 'two_factor_trusted_device_days',
    ];

    protected function casts(): array
    {
        return [
            'is_system_role' => 'boolean',
            'is_situational' => 'boolean',
            'two_factor_required' => 'boolean',
            'two_factor_allowed_methods' => 'array',
            'two_factor_trusted_device_allowed' => 'boolean',
        ];
    }

    /** @return string[] e.g. ['totp', 'email'] — empty/null in the DB means both are allowed. */
    public function twoFactorAllowedMethods(): array
    {
        return $this->two_factor_allowed_methods ?: ['totp', 'email'];
    }

    public function twoFactorTrustedDeviceDays(): int
    {
        return $this->two_factor_trusted_device_days ?? config('twofactor.trusted_device_days');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function screenPermissions(): HasMany
    {
        return $this->hasMany(RoleScreenPermission::class);
    }

    public function dataGroupPermissions(): HasMany
    {
        return $this->hasMany(RoleDataGroupPermission::class);
    }

    public function screens(): BelongsToMany
    {
        return $this->belongsToMany(Screen::class, 'role_screen_permissions')
            ->wherePivot('can_view', true)
            ->withTimestamps();
    }

    public function dataGroups(): BelongsToMany
    {
        return $this->belongsToMany(DataGroup::class, 'role_data_group_permissions')
            ->withPivot(['scope', 'level'])
            ->withTimestamps();
    }

    /** Deleting/editing a system role is blocked everywhere (spec A2 guardrail). */
    public function isEditable(): bool
    {
        return ! $this->is_system_role;
    }

    /**
     * Duplicate this role's full permission matrix into a brand-new custom
     * role — the role-builder's "Clone role" feature (spec A2): "a new role
     * is typically built as 'start from HR Officer, then add X' rather than
     * from a blank matrix."
     */
    public function cloneAs(string $name, string $slug): self
    {
        $clone = self::create([
            'name' => $name,
            'slug' => $slug,
            'description' => "Cloned from {$this->name}.",
            'is_system_role' => false,
            'is_situational' => false,
        ]);

        foreach ($this->screenPermissions as $p) {
            $clone->screenPermissions()->create([
                'screen_id' => $p->screen_id,
                'can_view' => $p->can_view,
            ]);
        }

        foreach ($this->dataGroupPermissions as $p) {
            $clone->dataGroupPermissions()->create([
                'data_group_id' => $p->data_group_id,
                'scope' => $p->scope,
                'level' => $p->level,
            ]);
        }

        return $clone;
    }
}
