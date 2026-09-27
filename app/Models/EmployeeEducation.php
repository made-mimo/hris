<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEducation extends Model
{
    protected $fillable = ['employee_id', 'institution', 'qualification', 'education_level_id', 'field_of_study', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'education_level_id');
    }
}
