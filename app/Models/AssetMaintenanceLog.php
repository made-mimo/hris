<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec E2: "a history of events behind the 'In Repair' status, rather than that status standing alone with nothing behind it." */
class AssetMaintenanceLog extends Model
{
    use Auditable;

    protected $fillable = ['asset_id', 'log_date', 'description', 'cost', 'vendor'];

    protected function casts(): array
    {
        return ['log_date' => 'date', 'cost' => 'decimal:2'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
