<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Spec D3: "a threaded set of Case Responses (each tagged as a direct response or an HR follow-up question)." */
class CaseResponse extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    protected $fillable = ['disciplinary_case_id', 'responded_by', 'type', 'body'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'disciplinary_case_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
