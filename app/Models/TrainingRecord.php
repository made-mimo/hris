<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TrainingRecord extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const STATUSES = ['planned', 'in_progress', 'completed'];

    protected $fillable = [
        'employee_id', 'title', 'provider', 'category', 'start_date', 'end_date',
        'duration', 'status', 'performance_goal_id',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('certificate')->singleFile();
    }

    public function certificateUrl(): ?string
    {
        return $this->getFirstMediaUrl('certificate') ?: null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Spec B3: "edit/delete eligibility ... computed per record (the owning
     * employee; HR/Admin always; a supervisor only for their own still-
     * planned entries) ... surfaced to the UI without a second round-trip" —
     * a plain method the UI calls inline while rendering the list, not an
     * API round-trip per row.
     */
    public function canBeEditedBy(User $user): bool
    {
        if ($user->isAdmin() || $user->isHr()) {
            return true;
        }

        if ($user->employee?->id === $this->employee_id) {
            return true;
        }

        if ($this->status === 'planned' && $this->employee?->supervisor_id === $user->employee?->id) {
            return true;
        }

        return false;
    }
}
