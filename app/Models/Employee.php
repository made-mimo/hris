<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Employee extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'supervisor_id',
        'employee_id',
        'first_name',
        'last_name',
        'initials',
        'job_title',
        'department',
        'location',
        'hire_date',
        'clocked_in',
        'clocked_in_at',
    ];

    /**
     * Profile photo storage (spec Section 3.5): Laravel's Storage abstraction
     * via spatie/laravel-medialibrary rather than a raw path column — a
     * single-file collection so a new upload replaces (and versions over)
     * the previous one instead of leaking orphaned files.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'clocked_in' => 'boolean',
            'clocked_in_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function avatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }

    /** Balance as of today: entitled minus taken/scheduled, per leave type, for the current leave year. Spec C1 — always computed on demand, never cached. */
    public function leaveBalance(LeaveType $type, ?int $year = null): array
    {
        $year ??= now()->year;

        $entitled = (float) $this->leaveEntitlements()
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->sum('entitled_days');

        // Default policy (spec C1): only approved (taken + scheduled) leave counts against
        // balance — a still-pending request does not, until it clears approval.
        $used = (float) $this->leaveRequests()
            ->where('leave_type_id', $type->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days');

        return [
            'entitled' => $entitled,
            'used' => $used,
            'available' => max(0, $entitled - $used),
        ];
    }
}
