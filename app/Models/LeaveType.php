<?php

namespace App\Models;

use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'minimum_tenure_months',
        'standard_annual_days',
        'carries_over_at_year_end',
        'sort_order',
        'exclude_from_reports_if_unentitled',
        'carryover_cap_days',
    ];

    protected function casts(): array
    {
        return [
            'carries_over_at_year_end' => 'boolean',
            'exclude_from_reports_if_unentitled' => 'boolean',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }
}
