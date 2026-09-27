<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCustomFieldValue extends Model
{
    protected $fillable = ['employee_id', 'definition_id', 'value'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(EmployeeCustomFieldDefinition::class, 'definition_id');
    }
}
