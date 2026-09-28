<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasAssignmentHistory;
use App\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec E3: current assignment is to EITHER an employee OR a sub-unit (department) — mutually exclusive, enforced in App\Services\VehicleService. */
class Vehicle extends Model
{
    use Auditable, HasAssignmentHistory, HasCustomFields;

    public const STATUSES = ['active', 'in_service', 'retired'];

    protected $fillable = [
        'make', 'model', 'year', 'vin', 'engine_number', 'registration_number', 'color',
        'status', 'current_employee_id', 'current_sub_unit_id', 'assigned_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function customFieldSubjectType(): string
    {
        return 'vehicle';
    }

    public function currentEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_employee_id');
    }

    public function currentSubUnit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class, 'current_sub_unit_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(VehicleRenewal::class)->latest('issue_date');
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(VehicleFuelLog::class)->latest('log_date');
    }

    public function currentAssigneeLabel(): ?string
    {
        return $this->currentEmployee?->fullName() ?? $this->currentSubUnit?->name;
    }

    public function latestOdometerReading(): ?int
    {
        return $this->fuelLogs()->max('odometer_reading');
    }
}
