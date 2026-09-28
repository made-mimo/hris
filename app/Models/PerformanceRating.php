<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec D2: "per-reviewer, per-KPI Ratings (each bounded to that specific KPI's own scale)." */
class PerformanceRating extends Model
{
    protected $fillable = ['performance_reviewer_id', 'kpi_id', 'rating', 'comment'];

    protected function casts(): array
    {
        return ['rating' => 'decimal:2'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewer::class, 'performance_reviewer_id');
    }

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }
}
