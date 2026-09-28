<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use Auditable;

    public const STATUSES = ['not_started', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = [
        'employee_id', 'title', 'description', 'target_value', 'current_value', 'unit',
        'due_date', 'status', 'performance_review_id', 'training_record_id',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function performanceReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class);
    }

    public function trainingRecord(): BelongsTo
    {
        return $this->belongsTo(TrainingRecord::class);
    }

    public function progressPercent(): ?float
    {
        if ($this->target_value === null || (float) $this->target_value === 0.0) {
            return null;
        }

        return round((float) $this->current_value / (float) $this->target_value * 100, 1);
    }
}
