<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingOffboardingTemplate extends Model
{
    protected $fillable = ['name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnboardingOffboardingTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    /** Admin backlog item 6b/6c — every item's effort, summed in hours regardless of which unit each was entered in. */
    public function cumulativeHours(): float
    {
        return $this->items->sum(fn (OnboardingOffboardingTemplateItem $item) => $item->durationInHours());
    }

    /** e.g. "16h (2 working days)" — shown next to the template name so the total effort is visible without opening it. */
    public function cumulativeSummary(): string
    {
        $hours = $this->cumulativeHours();

        if ($hours <= 0) {
            return '0h';
        }

        $days = $hours / OnboardingOffboardingTemplateItem::WORKING_HOURS_PER_DAY;
        $daysLabel = rtrim(rtrim(number_format($days, 1), '0'), '.');

        return "{$hours}h ({$daysLabel} working day".($days == 1 ? '' : 's').')';
    }
}
