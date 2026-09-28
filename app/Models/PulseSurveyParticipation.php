<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec F8: "the system can report who has not yet responded...without exposing what any specific respondent answered — two separate, deliberately-unlinked records." */
class PulseSurveyParticipation extends Model
{
    protected $fillable = ['pulse_survey_run_id', 'employee_id', 'has_responded'];

    protected function casts(): array
    {
        return ['has_responded' => 'boolean'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PulseSurveyRun::class, 'pulse_survey_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
