<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PostComment extends Model
{
    protected $fillable = ['post_share_id', 'employee_id', 'body'];

    public function share(): BelongsTo
    {
        return $this->belongsTo(PostShare::class, 'post_share_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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
