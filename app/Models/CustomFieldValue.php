<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Spec E2/E3: "values stored generically as text, same trade-off noted in Section E3" — the field's declared type drives client-side input rendering only, never server-side typing. */
class CustomFieldValue extends Model
{
    protected $fillable = ['definition_id', 'valuable_type', 'valuable_id', 'value'];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }

    public function valuable(): MorphTo
    {
        return $this->morphTo();
    }
}
