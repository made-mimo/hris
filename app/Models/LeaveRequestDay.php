<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequestDay extends Model
{
    public const STATUSES = ['pending', 'scheduled', 'taken', 'rejected', 'cancelled', 'inert'];

    protected $fillable = [
        'leave_request_id', 'date', 'is_working_day', 'duration_type',
        'hours', 'day_value', 'status', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_working_day' => 'boolean',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(LeaveEntitlementConsumption::class);
    }
}
