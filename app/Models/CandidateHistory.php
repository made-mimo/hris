<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only pipeline audit trail (spec D1) — same const UPDATED_AT = null pattern as TimesheetActionLog. */
class CandidateHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['candidate_application_id', 'interview_id', 'performed_by', 'action', 'note'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'candidate_application_id');
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
