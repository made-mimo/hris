<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Spec F1: "Likes on shares and on comments (one per employee per target)" — enforced by a DB unique index on (employee_id, likeable_type, likeable_id). */
class PostLike extends Model
{
    protected $fillable = ['employee_id', 'likeable_type', 'likeable_id'];

    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
