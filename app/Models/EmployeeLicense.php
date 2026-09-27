<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLicense extends Model
{
    protected $fillable = ['employee_id', 'license_type_id', 'license_number', 'issue_date', 'expiry_date'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function licenseType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'license_type_id');
    }
}
