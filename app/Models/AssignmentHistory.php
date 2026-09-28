<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Spec E2/E3: "a fully gapless log of every employee [or department] who
 * has held the [asset/vehicle] and when" — shared by both registers.
 * `ended_at === null` means this is the currently open assignment period;
 * any reassignment closes the prior period and opens a new one (see
 * App\Services\AssetService / VehicleService).
 */
class AssignmentHistory extends Model
{
    protected $fillable = [
        'assignable_type', 'assignable_id', 'employee_id', 'sub_unit_id', 'started_at', 'ended_at',
        'override_reason', 'overridden_by_id',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_id');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function holderName(): string
    {
        return $this->employee?->fullName() ?? $this->subUnit?->name ?? 'Unassigned';
    }
}
