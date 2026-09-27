<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\LeaveEntitlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveEntitlement extends Model
{
    /** @use HasFactory<LeaveEntitlementFactory> */
    use HasFactory;

    public const BATCH_STANDARD = 'standard';

    public const BATCH_NEW_HIRE_PRORATED = 'new_hire_prorated';

    public const BATCH_CARRIED_OVER = 'carried_over';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'entitled_days',
        'batch_type',
        'effective_start_date',
        'effective_end_date',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(LeaveEntitlementConsumption::class);
    }

    public function consumedDays(): float
    {
        return (float) $this->consumptions()->sum('days_consumed');
    }

    public function remainingDays(): float
    {
        return max(0, (float) $this->entitled_days - $this->consumedDays());
    }

    /** The date this batch stops accepting new consumption — its own expiry if set, else the end of its effective range. */
    public function drawDownDeadline(): Carbon
    {
        return Carbon::parse($this->expires_at ?? $this->effective_end_date);
    }

    public function isOpenOn(Carbon $date): bool
    {
        $start = Carbon::parse($this->effective_start_date);
        $end = $this->drawDownDeadline();

        return $date->betweenIncluded($start, $end);
    }
}
