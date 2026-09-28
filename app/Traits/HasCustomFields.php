<?php

namespace App\Traits;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Spec E2/E3: shared custom-fields mechanism for Asset and Vehicle — see CustomFieldDefinition's own doc comment. Each consumer sets its own `customFieldSubjectType()`. */
trait HasCustomFields
{
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'valuable');
    }

    abstract public function customFieldSubjectType(): string;

    public function customFieldDefinitions()
    {
        return CustomFieldDefinition::where('subject_type', $this->customFieldSubjectType())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function customFieldValueFor(int $definitionId): ?string
    {
        return $this->customFieldValues->firstWhere('definition_id', $definitionId)?->value;
    }
}
