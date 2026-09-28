<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec D2: "a template applied to one subject employee, with a due date and a 'shared with subject' flag." */
class FeedbackCycle extends Model
{
    use Auditable;

    protected $fillable = ['feedback_template_id', 'subject_employee_id', 'initiated_by', 'due_date', 'shared_with_subject', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'shared_with_subject' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'subject_employee_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(FeedbackParticipant::class);
    }
}
