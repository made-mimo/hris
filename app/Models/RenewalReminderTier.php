<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalReminderTier extends Model
{
    protected $fillable = ['renewal_type_id', 'days_before_expiry'];

    public function renewalType(): BelongsTo
    {
        return $this->belongsTo(RenewalType::class);
    }
}
