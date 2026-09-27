<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlementConsumption extends Model
{
    protected $fillable = ['leave_request_day_id', 'leave_entitlement_id', 'days_consumed'];

    public function day(): BelongsTo
    {
        return $this->belongsTo(LeaveRequestDay::class, 'leave_request_day_id');
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(LeaveEntitlement::class, 'leave_entitlement_id');
    }
}
