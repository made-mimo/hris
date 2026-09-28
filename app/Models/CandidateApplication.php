<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Spec D1: the join between a Candidate and a Vacancy, carrying the pipeline
 * status — a full model (not a bare pivot) since it has its own workflow
 * state, interviews, and audit history.
 */
class CandidateApplication extends Model
{
    use Auditable;

    public const STATUSES = [
        'application_initiated', 'shortlisted', 'interview_scheduled', 'interview_passed',
        'job_offered', 'hired', 'interview_failed', 'offer_declined', 'rejected',
    ];

    protected $fillable = ['candidate_id', 'vacancy_id', 'status'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(CandidateHistory::class)->latest();
    }

    /** WorkflowEngine's opt-in extension point (see WorkflowEngine::actorTags()) — a hiring manager isn't the record's "owner" the way owner/supervisor is elsewhere, so this contributes a purpose-built tag instead of forcing Recruitment into that vocabulary. */
    public function workflowActorTags(User $user): array
    {
        $employee = $user->employee;

        if ($employee && $this->vacancy && (int) $this->vacancy->hiring_manager_id === $employee->id) {
            return ['hiring_manager'];
        }

        return [];
    }

    /** The exact, stable content the candidate's offer-letter signature is hashed against — no timestamps, so re-rendering this page never invalidates a prior signature. */
    public function offerLetterContent(): string
    {
        return sprintf(
            'Offer Letter | Application #%d | Candidate: %s | Position: %s | Positions available: %d',
            $this->id,
            $this->candidate->fullName(),
            $this->vacancy->title,
            $this->vacancy->position_count,
        );
    }

    public function stageLabel(): string
    {
        return match ($this->status) {
            'application_initiated' => 'Application received',
            'shortlisted' => 'Shortlisted',
            'interview_scheduled' => 'Interview scheduled',
            'interview_passed' => 'Interview passed',
            'job_offered' => 'Job offered',
            'hired' => 'Hired',
            'interview_failed' => 'Interview failed',
            'offer_declined' => 'Offer declined',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
