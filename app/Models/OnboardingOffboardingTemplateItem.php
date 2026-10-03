<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingOffboardingTemplateItem extends Model
{
    /** Matches OnboardingOffboardingTemplate::WORKING_HOURS_PER_DAY — kept on this class too since a single item's own hour-equivalent is meaningful on its own, not just as part of a template total. */
    public const WORKING_HOURS_PER_DAY = 8;

    protected $fillable = ['template_id', 'title', 'offset_days', 'duration_value', 'duration_unit', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingOffboardingTemplate::class, 'template_id');
    }

    /** This one task's effort, converted to hours regardless of which unit it was entered in. */
    public function durationInHours(): float
    {
        return $this->duration_unit === 'days'
            ? $this->duration_value * self::WORKING_HOURS_PER_DAY
            : (float) $this->duration_value;
    }
}
