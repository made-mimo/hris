<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function stageLabel(): string
    {
        return match ($this->status) {
            'pending_manager' => 'Line Manager approval',
            'pending_hr' => 'HR final approval',
            'approved' => 'Taken',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => $this->status,
        };
    }
}
