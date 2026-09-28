<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Interview extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    /** Spec D1: "A maximum of two interview rounds per candidate per vacancy is enforced." */
    public const MAX_ROUNDS_PER_APPLICATION = 2;

    protected $fillable = ['candidate_application_id', 'name', 'interview_date', 'interview_time', 'note'];

    protected function casts(): array
    {
        return ['interview_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('local');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'candidate_application_id');
    }

    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'interview_interviewer');
    }
}
