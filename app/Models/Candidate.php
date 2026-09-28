<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Candidate extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'application_mode', 'application_date', 'keyword_tags', 'consent_given',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'consent_given' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('local');
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class);
    }
}
