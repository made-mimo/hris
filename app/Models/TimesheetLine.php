<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetLine extends Model
{
    protected $fillable = [
        'timesheet_id', 'project_id', 'project_activity_id',
        'monday_hours', 'tuesday_hours', 'wednesday_hours', 'thursday_hours',
        'friday_hours', 'saturday_hours', 'sunday_hours',
    ];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ProjectActivity::class, 'project_activity_id');
    }

    public function totalHours(): float
    {
        return array_sum(array_map(fn ($col) => (float) $this->{$col}, Timesheet::DAY_COLUMNS));
    }
}
