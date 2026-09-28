<?php

namespace App\Models;

use App\Services\SignatureService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Spec E4: "immutable — each new version is an additive record, never an
 * overwrite." The file lives on the PRIVATE ('local') disk rather than the
 * app's usual public media disk — every version, restricted category or
 * not, is served exclusively through App\Http\Controllers\
 * PolicyDocumentFileController rather than a directly guessable public URL,
 * which is what lets restricted-category access control actually hold.
 */
class PolicyDocumentVersion extends Model implements HasMedia
{
    use InteractsWithMedia;

    const PURPOSE = 'policy_acknowledgement';

    protected $fillable = ['policy_document_id', 'version_label', 'change_notes', 'effective_date'];

    protected function casts(): array
    {
        return ['effective_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->useDisk('local')->singleFile();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PolicyDocument::class, 'policy_document_id');
    }

    /** The exact content a signer is acknowledging — stable per version, so a re-upload of the same version_label would still hash differently only if the version row itself changed, which it never does (immutable). */
    public function acknowledgementContent(): string
    {
        return "policy_document:{$this->policy_document_id}:version:{$this->id}:{$this->version_label}";
    }

    public function isAcknowledgedBy(User $user, SignatureService $signatures): bool
    {
        return $signatures->hasValidSignature($this, $user, self::PURPOSE, $this->acknowledgementContent());
    }
}
