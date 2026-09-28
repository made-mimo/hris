<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyRegistrationDocument extends Model
{
    protected $fillable = ['title', 'document_category_id', 'description', 'is_renewable'];

    protected function casts(): array
    {
        return ['is_renewable' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyRegistrationDocumentVersion::class, 'document_id')->latest('created_at');
    }

    public function currentVersion(): ?CompanyRegistrationDocumentVersion
    {
        return $this->versions()->first();
    }
}
