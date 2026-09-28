<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackResponse extends Model
{
    protected $fillable = ['feedback_participant_id', 'feedback_template_question_id', 'rating', 'comment'];

    protected function casts(): array
    {
        return ['rating' => 'decimal:2'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FeedbackParticipant::class, 'feedback_participant_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplateQuestion::class, 'feedback_template_question_id');
    }
}
