<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Backlog #11 — one entry in a template's question bank; a run then selects a subset of these (PulseSurveyRun::questions()). */
class PulseSurveyQuestion extends Model
{
    public const TYPES = ['scale', 'free_text'];

    public const SCALE_TYPES = ['likert_5', 'enps_0_10'];

    protected $fillable = ['pulse_survey_template_id', 'type', 'prompt', 'scale_type', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PulseSurveyTemplate::class, 'pulse_survey_template_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PulseSurveyAnswer::class);
    }

    public function runs(): BelongsToMany
    {
        return $this->belongsToMany(PulseSurveyRun::class, 'pulse_survey_run_question');
    }

    public function scaleMin(): int
    {
        return $this->scale_type === 'enps_0_10' ? 0 : 1;
    }

    public function scaleMax(): int
    {
        return $this->scale_type === 'enps_0_10' ? 10 : 5;
    }
}
