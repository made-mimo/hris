<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    use Auditable;

    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public const FORMATS = ['csv', 'pdf'];

    protected $fillable = [
        'name', 'report_type', 'config', 'recipients', 'format', 'frequency', 'is_active', 'created_by', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'recipients' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDue(): bool
    {
        if (! $this->last_run_at) {
            return true;
        }

        return match ($this->frequency) {
            'daily' => $this->last_run_at->lt(now()->subDay()),
            'weekly' => $this->last_run_at->lt(now()->subWeek()),
            'monthly' => $this->last_run_at->lt(now()->subMonth()),
        };
    }
}
