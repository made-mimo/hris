<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['auditable_type', 'auditable_id', 'action', 'actor_id', 'actor_label', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** A short, human label for the record this row is about, even after it's been deleted. */
    public function subjectLabel(): string
    {
        $short = class_basename($this->auditable_type);

        return "{$short} #{$this->auditable_id}";
    }
}
