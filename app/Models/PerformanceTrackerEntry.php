<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec D2: "an ongoing achievement log per employee, separate from formal reviews." */
class PerformanceTrackerEntry extends Model
{
    use Auditable;

    protected $fillable = ['employee_id', 'entry_date', 'sentiment', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
