<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec F8: "not linked to the identifiable employee in any UI or export" — deliberately no employee_id column. */
class PulseSurveyResponse extends Model
{
    protected $fillable = ['pulse_survey_run_id', 'scale_value', 'free_text', 'is_flagged'];

    protected function casts(): array
    {
        return ['is_flagged' => 'boolean'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PulseSurveyRun::class, 'pulse_survey_run_id');
    }
}
