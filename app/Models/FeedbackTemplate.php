<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackTemplate extends Model
{
    use Auditable;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackTemplateQuestion::class)->orderBy('sort_order');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(FeedbackCycle::class);
    }
}
