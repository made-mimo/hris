<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\ExpenseClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseClaim extends Model
{
    /** @use HasFactory<ExpenseClaimFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'reference',
        'employee_id',
        'claim_event_id',
        'currency',
        'travel_advance_amount',
        'status',
        'submitted_at',
        'manager_approved_by',
        'manager_approved_at',
        'hr_approved_by',
        'hr_approved_at',
        'rejection_reason',
        'rejected_at',
        'payment_method',
        'payment_reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'travel_advance_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'manager_approved_at' => 'datetime',
            'hr_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function claimEvent(): BelongsTo
    {
        return $this->belongsTo(ClaimEvent::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseClaimLine::class);
    }

    public function total(): float
    {
        return (float) $this->lines->sum('amount');
    }

    public function net(): float
    {
        return $this->total() - (float) $this->travel_advance_amount;
    }

    public function stageLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending_manager' => 'Line Manager approval',
            'pending_hr' => 'HR review',
            'approved' => 'Finance · payment',
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            default => $this->status,
        };
    }
}
