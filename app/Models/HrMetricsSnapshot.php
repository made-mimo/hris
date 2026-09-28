<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Spec F2: "one row per calendar day, computed nightly" — see App\Services\HrMetricsService. */
class HrMetricsSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date', 'active_headcount', 'new_hires_trailing_30d', 'terminations_trailing_30d',
        'turnover_rate_percent', 'open_requisitions', 'pending_leave_requests', 'average_tenure_years',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'turnover_rate_percent' => 'decimal:2',
            'average_tenure_years' => 'decimal:2',
        ];
    }
}
