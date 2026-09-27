<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id', 'punch_in_at_utc', 'punch_in_at_local', 'punch_in_timezone',
        'punch_out_at_utc', 'punch_out_at_local', 'punch_out_timezone',
        'is_proxy_punch', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'punch_in_at_utc' => 'datetime',
            'punch_in_at_local' => 'datetime',
            'punch_out_at_utc' => 'datetime',
            'punch_out_at_local' => 'datetime',
            'is_proxy_punch' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isOpen(): bool
    {
        return $this->punch_out_at_utc === null;
    }

    public function durationHours(): ?float
    {
        if (! $this->punch_out_at_utc) {
            return null;
        }

        return round($this->punch_in_at_utc->diffInMinutes($this->punch_out_at_utc) / 60, 2);
    }
}
