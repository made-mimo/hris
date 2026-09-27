<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingOffboardingTemplate extends Model
{
    protected $fillable = ['name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnboardingOffboardingTemplateItem::class, 'template_id')->orderBy('sort_order');
    }
}
