<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\ExpenseClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ExpenseClaim extends Model implements HasMedia
{
    /** @use HasFactory<ExpenseClaimFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

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
        'second_approved_by',
        'second_approved_at',
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
            'second_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts');
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

    /** Spec E1: "an Admin-configurable claim-amount threshold...routes any claim above it through a second, higher-level approver." Unset threshold = single-level approval, per spec's default. */
    public function requiresSecondApproval(): bool
    {
        $threshold = Setting::current()->expense_claim_second_approval_threshold;

        return $threshold !== null && $this->total() > (float) $threshold;
    }

    /** Spec E1: "claim(s) subsequently submitted against that same event display the outstanding advance balance...on approval the claim's total is automatically reconciled against it." Matched by (employee, claim event) since the claim didn't exist when the advance was raised. */
    public function reconcilingAdvance(): ?TravelAdvance
    {
        return TravelAdvance::where('employee_id', $this->employee_id)
            ->where('claim_event_id', $this->claim_event_id)
            ->where('status', 'paid')
            ->first();
    }

    public function reconciliationVariance(): ?float
    {
        $advance = $this->reconcilingAdvance();

        return $advance ? round($this->total() - (float) $advance->amount, 2) : null;
    }

    public function stageLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending_manager' => 'Line Manager approval',
            'pending_hr' => 'HR review',
            'pending_second_approval' => 'Second approval (high value)',
            'approved' => 'Finance · payment',
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            default => $this->status,
        };
    }
}
