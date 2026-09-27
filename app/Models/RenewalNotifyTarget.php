<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalNotifyTarget extends Model
{
    protected $fillable = ['renewal_type_id', 'role_id', 'user_id'];

    public function renewalType(): BelongsTo
    {
        return $this->belongsTo(RenewalType::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
