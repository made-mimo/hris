<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleDataGroupPermission extends Model
{
    protected $fillable = ['role_id', 'data_group_id', 'scope', 'level'];

    public const SCOPES = ['none', 'self', 'self_subordinates', 'all'];

    public const LEVELS = ['none', 'view', 'view_edit', 'view_edit_delete'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function dataGroup(): BelongsTo
    {
        return $this->belongsTo(DataGroup::class);
    }

    public function scopeRank(): int
    {
        return array_search($this->scope, self::SCOPES, true) ?: 0;
    }

    public function levelRank(): int
    {
        return array_search($this->level, self::LEVELS, true) ?: 0;
    }
}
