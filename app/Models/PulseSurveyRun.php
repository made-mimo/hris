<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec F8: "a scheduled instance of a template — launch date, close date, target audience scope." */
class PulseSurveyRun extends Model
{
    protected $fillable = [
        'pulse_survey_template_id', 'launch_date', 'close_date', 'audience_scope',
        'audience_sub_unit_id', 'audience_location_id', 'status',
        'is_recurring', 'recurrence_months', 'parent_run_id',
    ];

    protected function casts(): array
    {
        return ['launch_date' => 'date', 'close_date' => 'date', 'is_recurring' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PulseSurveyTemplate::class, 'pulse_survey_template_id');
    }

    public function audienceSubUnit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class, 'audience_sub_unit_id');
    }

    public function audienceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'audience_location_id');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(PulseSurveyParticipation::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PulseSurveyResponse::class);
    }

    public function audienceLabel(): string
    {
        return match ($this->audience_scope) {
            'department' => $this->audienceSubUnit?->name ?? 'Department',
            'location' => $this->audienceLocation?->name ?? 'Location',
            default => 'All employees',
        };
    }
}
