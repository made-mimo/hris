<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Spec E2: "an asset...can carry one or more warranty/service-contract records, registered against the Renewal & Compliance Reminder Engine...exactly like Vehicle Renewals." */
class AssetWarranty extends Model
{
    use Auditable;

    protected $fillable = ['asset_id', 'provider', 'contract_number', 'issue_date', 'expiry_date', 'notes'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function renewable(): MorphOne
    {
        return $this->morphOne(Renewable::class, 'renewable');
    }
}
