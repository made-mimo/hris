<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Backlog #11 — one answer to one question within an anonymous response envelope. Still deliberately no employee_id anywhere in this chain (PulseSurveyResponse doc comment). */
class PulseSurveyAnswer extends Model
{
    protected $fillable = ['pulse_survey_response_id', 'pulse_survey_question_id', 'scale_value', 'free_text', 'is_flagged'];

    protected function casts(): array
    {
        return ['is_flagged' => 'boolean'];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(PulseSurveyResponse::class, 'pulse_survey_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PulseSurveyQuestion::class, 'pulse_survey_question_id');
    }
}
