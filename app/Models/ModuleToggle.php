<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Spec B1: system-level module enable/disable toggles with dependent UI (e.g. the sidebar nav) automatically hiding when a module is disabled — see App\Services\PermissionService / the sidebar partial for the consumer side. */
class ModuleToggle extends Model
{
    protected $fillable = ['key', 'label', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public static function isEnabled(string $key): bool
    {
        return (bool) (static::where('key', $key)->value('is_enabled') ?? true);
    }
}
