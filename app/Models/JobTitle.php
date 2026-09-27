<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Spec B1: "Job Titles (with an optional attached job-specification document)." */
class JobTitle extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('job_spec')->singleFile();
    }

    public function jobSpecUrl(): ?string
    {
        return $this->getFirstMediaUrl('job_spec') ?: null;
    }

    public function jobSpecName(): ?string
    {
        return $this->getFirstMedia('job_spec')?->name;
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
