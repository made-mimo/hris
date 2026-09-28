<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Spec F1: every *appearance* of a Post in the feed — the original posting and every reshare are both a PostShare, each with its own independent like/comment thread. */
class PostShare extends Model
{
    protected $fillable = ['post_id', 'employee_id', 'caption', 'like_count', 'comment_count'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class)->oldest();
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(PostLike::class, 'likeable');
    }

    public function isLikedBy(Employee $employee): bool
    {
        return $this->likes()->where('employee_id', $employee->id)->exists();
    }
}
