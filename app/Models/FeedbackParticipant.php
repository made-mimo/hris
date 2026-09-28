<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec D2: "invited raters, each tagged by relationship — manager/peer/direct-report/self." */
class FeedbackParticipant extends Model
{
    public const RELATIONSHIPS = ['manager', 'peer', 'direct_report', 'self'];

    protected $fillable = ['feedback_cycle_id', 'rater_employee_id', 'relationship_tag', 'status'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(FeedbackCycle::class, 'feedback_cycle_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rater_employee_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class);
    }

    /** Spec D2: "peer and direct-report responses are pooled...unattributed comments (rater identity never exposed for those two relationship types), while manager and self responses remain individually attributed." */
    public function isAttributed(): bool
    {
        return in_array($this->relationship_tag, ['manager', 'self'], true);
    }
}
