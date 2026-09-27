<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLanguage extends Model
{
    public const LEVELS = ['basic', 'intermediate', 'fluent', 'native'];

    protected $fillable = ['employee_id', 'language_id', 'reading_level', 'writing_level', 'speaking_level'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'language_id');
    }
}
