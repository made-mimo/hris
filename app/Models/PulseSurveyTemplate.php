<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Backlog #11 — a template is now a reusable question bank (see
 * PulseSurveyQuestion) rather than a hard-coded scale+free-text pair, kept
 * short in practice by capping how many of its questions a single run may
 * select (MAX_QUESTIONS_PER_RUN) rather than by only ever having two to
 * choose from.
 */
class PulseSurveyTemplate extends Model
{
    public const MAX_QUESTIONS_PER_RUN = 5;

    protected $fillable = ['name'];

    public function runs(): HasMany
    {
        return $this->hasMany(PulseSurveyRun::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PulseSurveyQuestion::class)->orderBy('sort_order');
    }
}
