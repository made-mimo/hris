<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasAssignmentHistory;
use App\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec E2: unique tag, categorized, current-assignment tracking. Status/assignee consistency is enforced server-side in AssetService, never as an incidental side effect of an unrelated field update. */
class Asset extends Model
{
    use Auditable, HasAssignmentHistory, HasCustomFields;

    public const STATUSES = ['available', 'assigned', 'in_repair', 'retired'];

    protected $fillable = [
        'tag', 'name', 'asset_category_id', 'serial_number', 'purchase_date',
        'purchase_cost', 'status', 'current_employee_id', 'assigned_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'assigned_at' => 'datetime',
        ];
    }

    public function customFieldSubjectType(): string
    {
        return 'asset';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function currentEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_employee_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class)->latest('log_date');
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(AssetWarranty::class);
    }
}
