<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec E2/E3: "Admin-definable custom fields, matching Vehicle Fleet Management for consistency between the two registers" — one shared definition table, discriminated by subject_type. */
class CustomFieldDefinition extends Model
{
    public const SUBJECT_TYPES = ['asset', 'vehicle'];

    public const FIELD_TYPES = ['text', 'number', 'date', 'select'];

    protected $fillable = ['subject_type', 'label', 'field_type', 'options', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'definition_id');
    }
}
