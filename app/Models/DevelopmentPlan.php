<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentPlan extends Model
{
    public const STATUSES = ['planned', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = ['employee_id', 'goal', 'target_skill_or_role', 'target_date', 'status'];

    protected function casts(): array
    {
        return ['target_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
