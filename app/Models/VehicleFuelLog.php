<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleFuelLog extends Model
{
    use Auditable;

    protected $fillable = ['vehicle_id', 'log_date', 'odometer_reading', 'fuel_cost', 'notes'];

    protected function casts(): array
    {
        return ['log_date' => 'date', 'fuel_cost' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
