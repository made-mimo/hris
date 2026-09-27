<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingOffboardingTemplateItem extends Model
{
    protected $fillable = ['template_id', 'title', 'offset_days', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingOffboardingTemplate::class, 'template_id');
    }
}
