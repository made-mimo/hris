<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Spec D1: the optional headcount pre-approval gate — approving auto-creates the Vacancy (see RecruitmentService::decideRequisition()). */
class Requisition extends Model
{
    use Auditable;

    public const STATUSES = ['requested', 'approved', 'rejected'];

    protected $fillable = [
        'title', 'job_title_id', 'position_count', 'justification', 'requested_by', 'hiring_manager_id',
        'status', 'decision_comment', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function vacancy(): HasOne
    {
        return $this->hasOne(Vacancy::class);
    }
}
