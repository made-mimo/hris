<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RenewalType extends Model
{
    protected $fillable = ['key', 'label'];

    public function tiers(): HasMany
    {
        return $this->hasMany(RenewalReminderTier::class)->orderBy('days_before_expiry', 'desc');
    }

    public function notifyTargets(): HasMany
    {
        return $this->hasMany(RenewalNotifyTarget::class);
    }

    public function renewables(): HasMany
    {
        return $this->hasMany(Renewable::class);
    }
}
