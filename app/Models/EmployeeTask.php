<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTask extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'done'];

    protected $fillable = ['employee_id', 'template_item_id', 'kind', 'title', 'due_date', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(OnboardingOffboardingTemplateItem::class, 'template_item_id');
    }
}
