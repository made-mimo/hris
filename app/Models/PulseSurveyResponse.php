<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Spec F8: "not linked to the identifiable employee in any UI or export" —
 * deliberately no employee_id column. Backlog #11: this row is now just the
 * anonymous "submission envelope" for one respondent's answers to a run's
 * selected questions — the answers themselves live in PulseSurveyAnswer.
 */
class PulseSurveyResponse extends Model
{
    protected $fillable = ['pulse_survey_run_id'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PulseSurveyRun::class, 'pulse_survey_run_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PulseSurveyAnswer::class);
    }
}
