<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A tamper-evident consent record (spec A8): once created, nothing about a
 * SignatureEvent should ever be edited — a later document revision gets a
 * fresh SignatureEvent against the new content hash, never a mutation of
 * this one. Drawn-signature images go through Media Library (spec 3.5),
 * same pattern as Employee's avatar / Setting's logo.
 */
class SignatureEvent extends Model implements HasMedia
{
    use InteractsWithMedia;

    public $timestamps = false;

    protected $fillable = ['signable_type', 'signable_id', 'signer_id', 'signer_name', 'signer_email', 'purpose', 'content_hash', 'method', 'ip_address', 'user_agent', 'signed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    /** Spec D1: an offer-letter signer (a Candidate) has no User account yet — falls back to the captured external name. */
    public function signerDisplayName(): string
    {
        return $this->signer?->name ?? $this->signer_name ?? 'Unknown';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('signature_image')->singleFile();
    }

    public function signatureImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('signature_image') ?: null;
    }
}
