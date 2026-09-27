<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeCustomFieldDefinition extends Model
{
    public const TABS = ['personal', 'contact', 'job', 'qualifications'];

    public const TYPES = ['text', 'select'];

    protected $fillable = ['label', 'field_type', 'options', 'tab', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(EmployeeCustomFieldValue::class, 'definition_id');
    }
}
