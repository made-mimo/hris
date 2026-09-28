<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Spec E3: "re-renewing creates a new row rather than overwriting the old one, preserving history" — each row registers its own Renewable; VehicleService retires the prior row's Renewable when a new one supersedes it. */
class VehicleRenewal extends Model
{
    use Auditable;

    protected $fillable = [
        'vehicle_id', 'label', 'provider', 'reference_number', 'issue_date', 'expiry_date',
        'notes', 'mileage_interval', 'due_at_mileage',
    ];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function renewable(): MorphOne
    {
        return $this->morphOne(Renewable::class, 'renewable');
    }

    public function isMileageDue(?int $currentOdometer): bool
    {
        return $this->due_at_mileage !== null && $currentOdometer !== null && $currentOdometer >= $this->due_at_mileage;
    }
}
