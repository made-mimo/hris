<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec D2: "Inactive → Activated → In Progress → Completed, while the self-evaluation track progresses independently of the supervisor track until final evaluation collapses both." */
class PerformanceReview extends Model
{
    use Auditable;

    public const STATUSES = ['inactive', 'activated', 'in_progress', 'completed'];

    protected $fillable = [
        'employee_id', 'job_title_id', 'sub_unit_id', 'status', 'review_period_start', 'review_period_end',
        'due_date', 'activated_at', 'completed_at', 'final_comment', 'final_rating',
    ];

    protected function casts(): array
    {
        return [
            'review_period_start' => 'date',
            'review_period_end' => 'date',
            'due_date' => 'date',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_rating' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class);
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(PerformanceReviewer::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function supervisorReviewer(): ?PerformanceReviewer
    {
        return $this->reviewers->firstWhere('group', 'supervisor');
    }

    public function selfReviewer(): ?PerformanceReviewer
    {
        return $this->reviewers->firstWhere('group', 'self');
    }
}
