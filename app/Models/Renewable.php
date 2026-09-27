<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The generic "this thing has an expiry date" record any future consuming
 * module (Vehicle Renewals, Asset warranties, Company Registration
 * Documents — spec E2/E3/E5, all Phase 4) attaches to via `renewable_type`/
 * `renewable_id`, rather than each module tracking its own expiry/reminder
 * state. See App\Services\RenewalReminderEngine.
 */
class Renewable extends Model
{
    protected $fillable = ['renewable_type', 'renewable_id', 'renewal_type_id', 'expiry_date', 'status', 'fired_tier_ids', 'last_expired_alert_at'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'fired_tier_ids' => 'array',
            'last_expired_alert_at' => 'datetime',
        ];
    }

    public function renewalType(): BelongsTo
    {
        return $this->belongsTo(RenewalType::class);
    }

    public function renewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }
}
