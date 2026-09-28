<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec E1's Travel Advance & Reconciliation: "requested ahead of the trip
 * and later linked to the claim(s) it is reconciled against" — matched by
 * (employee, claim_event) rather than an explicit per-claim FK, since the
 * claim(s) it will reconcile against don't exist yet when the advance is
 * raised.
 */
class TravelAdvance extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id', 'claim_event_id', 'amount', 'currency', 'status',
        'rejection_reason', 'payment_method', 'payment_reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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

    public function isPaidOut(): bool
    {
        return $this->status === 'paid';
    }

    public function stageLabel(): string
    {
        return match ($this->status) {
            'pending_manager' => 'Line Manager approval',
            'pending_hr' => 'HR review',
            'approved' => 'Finance · payment',
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
