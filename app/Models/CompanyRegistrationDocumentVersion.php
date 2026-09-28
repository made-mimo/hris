<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Spec E5: "every renewal is a new version...never an overwrite" — same additive-versioning pattern as PolicyDocumentVersion (E4), and file storage follows the same private-disk precedent. */
class CompanyRegistrationDocumentVersion extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['document_id', 'issue_date', 'expiry_date', 'notes'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->useDisk('local')->singleFile();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyRegistrationDocument::class, 'document_id');
    }

    public function renewable(): MorphOne
    {
        return $this->morphOne(Renewable::class, 'renewable');
    }
}
