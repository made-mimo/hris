<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeImmigrationRecord extends Model
{
    protected $fillable = ['employee_id', 'document_type', 'document_number', 'issue_date', 'expiry_date', 'status', 'review_date'];

    protected function casts(): array
    {
        return [
            'document_number' => 'encrypted',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'review_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
