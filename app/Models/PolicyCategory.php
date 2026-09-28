<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec E4: "a Policy Category can be marked restricted" — gates every version filed under it via App\Services\PolicyService. */
class PolicyCategory extends Model
{
    protected $fillable = ['name', 'description', 'sort_order', 'is_restricted'];

    protected function casts(): array
    {
        return ['is_restricted' => 'boolean'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PolicyDocument::class);
    }

    public function fileAccessGrants(): HasMany
    {
        return $this->hasMany(PolicyFileAccessGrant::class);
    }
}
