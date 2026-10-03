<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'reference',
        'employee_id',
        'leave_type_id',
        'reliever_employee_id',
        'start_date',
        'end_date',
        'duration_type',
        'days',
        'reason',
        'status',
        'manager_approved_by',
        'manager_approved_at',
        'hr_approved_by',
        'hr_approved_at',
        'rejection_reason',
        'rejected_at',
        'hr_comment',
        'is_assigned',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:1',
            'manager_approved_at' => 'datetime',
            'hr_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_assigned' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function relieverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reliever_employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function hrApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    /** Named to avoid colliding with the `days` column (the cached total) — Eloquent would always resolve `$request->days` to the attribute, never this relation, if it were named the same. */
    public function requestDays(): HasMany
    {
        return $this->hasMany(LeaveRequestDay::class);
    }

    public function workingDays(): HasMany
    {
        return $this->requestDays()->where('is_working_day', true);
    }

    public function stageLabel(): string
    {
        return match ($this->status) {
            'pending_manager' => 'Line Manager approval',
            'pending_hr' => 'HR final approval',
            'approved' => 'Taken',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'restricted' => 'Restricted (leave type deleted) — cancel only',
            default => $this->status,
        };
    }
}
