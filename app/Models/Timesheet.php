<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    use Auditable;

    public const DAY_COLUMNS = ['monday_hours', 'tuesday_hours', 'wednesday_hours', 'thursday_hours', 'friday_hours', 'saturday_hours', 'sunday_hours'];

    protected $fillable = [
        'employee_id', 'week_start_date', 'week_end_date', 'status',
        'submitted_at', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TimesheetLine::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(TimesheetActionLog::class)->latest('created_at');
    }

    public function totalHours(): float
    {
        return (float) $this->lines->sum(fn (TimesheetLine $line) => $line->totalHours());
    }

    public function logAction(?User $actor, string $action, ?string $note = null): void
    {
        $this->actionLogs()->create(['actor_id' => $actor?->id, 'action' => $action, 'note' => $note]);
    }
}
