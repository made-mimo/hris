<?php

namespace App\Traits;

use App\Models\AssignmentHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Spec E2/E3: shared assignment-history mechanism for Asset and Vehicle — see AssignmentHistory's own doc comment. */
trait HasAssignmentHistory
{
    public function assignmentHistory(): MorphMany
    {
        return $this->morphMany(AssignmentHistory::class, 'assignable')->latest('started_at');
    }

    public function currentAssignment(): ?AssignmentHistory
    {
        return $this->assignmentHistory()->whereNull('ended_at')->first();
    }
}
