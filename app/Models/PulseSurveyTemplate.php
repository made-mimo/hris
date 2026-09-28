<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec F8: "a short set of questions...kept short by design so completion rates stay high" — one scale question plus one optional free-text question, deliberately not a flexible question-builder. */
class PulseSurveyTemplate extends Model
{
    public const SCALE_TYPES = ['likert_5', 'enps_0_10'];

    protected $fillable = ['name', 'primary_question', 'scale_type', 'free_text_question'];

    public function runs(): HasMany
    {
        return $this->hasMany(PulseSurveyRun::class);
    }
}
