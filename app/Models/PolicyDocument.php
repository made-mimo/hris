<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyDocument extends Model
{
    protected $fillable = ['title', 'policy_category_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PolicyCategory::class, 'policy_category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PolicyDocumentVersion::class)->latest('effective_date');
    }

    public function currentVersion(): ?PolicyDocumentVersion
    {
        return $this->versions()->first();
    }
}
