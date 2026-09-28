<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Spec D2: "soft-deleted rather than hard-deleted once used" — always soft-deleted via the UI, never a real DELETE. */
class Kpi extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['title', 'min_scale', 'max_scale', 'job_title_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_scale' => 'decimal:2', 'max_scale' => 'decimal:2'];
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function isDefault(): bool
    {
        return $this->job_title_id === null;
    }

    /** Every KPI applicable to a given job title — its job-title-specific ones plus every default. */
    public static function applicableTo(?int $jobTitleId)
    {
        return static::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('job_title_id')->when($jobTitleId, fn ($q2) => $q2->orWhere('job_title_id', $jobTitleId)))
            ->get();
    }
}
