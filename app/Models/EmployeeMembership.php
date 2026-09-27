<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMembership extends Model
{
    protected $fillable = ['employee_id', 'membership_body_id', 'subscription_fee', 'renewal_date'];

    protected function casts(): array
    {
        return ['renewal_date' => 'date', 'subscription_fee' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function membershipBody(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'membership_body_id');
    }
}
