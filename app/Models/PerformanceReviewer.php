<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec D2: "one row per reviewer per review, grouped as 'Supervisor' or 'Employee/Self,' each with independent progress status." */
class PerformanceReviewer extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'completed'];

    protected $fillable = ['performance_review_id', 'employee_id', 'group', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(PerformanceRating::class);
    }
}
