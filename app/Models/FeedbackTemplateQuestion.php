<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackTemplateQuestion extends Model
{
    protected $fillable = ['feedback_template_id', 'question_text', 'min_scale', 'max_scale', 'sort_order'];

    protected function casts(): array
    {
        return ['min_scale' => 'decimal:2', 'max_scale' => 'decimal:2'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id');
    }
}
