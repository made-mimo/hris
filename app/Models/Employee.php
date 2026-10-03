<?php

namespace App\Models;

use App\Services\LeaveBalanceService;
use App\Traits\Auditable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Employee extends Model implements HasMedia
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    /** Auditable diffs post-cast values, so an encrypted field would otherwise leak its decrypted plaintext into audit_logs.changes — excluded here for the same reason the trait always redacts password/token fields. */
    protected array $auditExcept = ['government_id_number'];

    protected $fillable = [
        'user_id',
        'supervisor_id',
        'employee_id',
        'first_name',
        'last_name',
        'initials',
        'job_title_id',
        'sub_unit_id',
        'location_id',
        'hire_date',
        'date_of_birth',
        'gender',
        'marital_status',
        'nationality_id',
        'government_id_type',
        'government_id_number',
        'driving_license_number',
        'home_address',
        'home_city_state',
        'home_country_id',
        'phone_home',
        'phone_mobile',
        'personal_email',
        'work_email',
        'employment_status_id',
        'job_category_id',
        'contract_start_date',
        'contract_end_date',
    ];

    /**
     * Profile photo storage (spec Section 3.5): Laravel's Storage abstraction
     * via spatie/laravel-medialibrary rather than a raw path column — a
     * single-file collection so a new upload replaces (and versions over)
     * the previous one instead of leaking orphaned files. `documents` is a
     * second, non-single-file collection for spec B2's per-tab attachments
     * (custom properties `tab` and `description` tag each upload).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        // 'documents' can hold ID/immigration-adjacent uploads (spec B2's
        // per-tab attachments) — private disk, unlike avatar (low
        // sensitivity, intentionally visible across the app as other
        // employees' avatars).
        $this->addMediaCollection('documents')->useDisk('local');
    }

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'date_of_birth' => 'date',
            'government_id_number' => 'encrypted',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'is_gdpr_purged' => 'boolean',
            'gdpr_purged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function hiringManagerVacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class, 'hiring_manager_id');
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function performanceTrackerEntries(): HasMany
    {
        return $this->hasMany(PerformanceTrackerEntry::class);
    }

    public function performanceTrackerReviewers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'performance_tracker_reviewers', 'employee_id', 'reviewer_id');
    }

    public function disciplinaryCases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class);
    }

    public function casesRaised(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class, 'raised_by');
    }

    public function interviews(): BelongsToMany
    {
        return $this->belongsToMany(Interview::class, 'interview_interviewer');
    }

    public function nationality(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'nationality_id');
    }

    public function homeCountry(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'home_country_id');
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'employment_status_id');
    }

    public function jobCategory(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'job_category_id');
    }

    /**
     * The real many-to-many reporting graph (spec B2) — `supervisor_id`
     * above stays as the single "primary direct supervisor" that
     * WorkflowEngine/approval-routing/PermissionService's self_subordinates
     * scope depend on for exactly one purpose (who approves this person's
     * requests); this pivot is the source of truth for everything else
     * (multi-supervisor UI, the org chart).
     */
    public function supervisorLinks(): HasMany
    {
        return $this->hasMany(EmployeeSupervisor::class, 'employee_id');
    }

    public function subordinateLinks(): HasMany
    {
        return $this->hasMany(EmployeeSupervisor::class, 'supervisor_id');
    }

    public function allSupervisors(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_supervisors', 'employee_id', 'supervisor_id')
            ->withPivot('reporting_method')
            ->withTimestamps();
    }

    public function allSubordinates(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_supervisors', 'supervisor_id', 'employee_id')
            ->withPivot('reporting_method')
            ->withTimestamps();
    }

    /**
     * Re-derives `supervisor_id` from the pivot's direct-method rows
     * whenever the multi-supervisor graph changes — keeps every existing
     * single-supervisor consumer (workflow routing, approvals) correct
     * without them needing to know the pivot exists. Picks the
     * lowest-id direct supervisor as "primary" when more than one exists.
     */
    public function syncPrimarySupervisor(): void
    {
        $primaryId = $this->supervisorLinks()->where('reporting_method', 'direct')->orderBy('supervisor_id')->value('supervisor_id');
        $this->update(['supervisor_id' => $primaryId]);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(EmployeeDependent::class);
    }

    public function immigrationRecords(): HasMany
    {
        return $this->hasMany(EmployeeImmigrationRecord::class);
    }

    public function compensations(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(EmployeeLanguage::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(EmployeeLicense::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(EmployeeMembership::class);
    }

    public function workExperience(): HasMany
    {
        return $this->hasMany(EmployeeWorkExperience::class);
    }

    public function terminations(): HasMany
    {
        return $this->hasMany(EmployeeTermination::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(EmployeeCustomFieldValue::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function projectAssignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function currentPunch(): ?AttendanceRecord
    {
        return $this->attendanceRecords()->whereNull('punch_out_at_utc')->latest('punch_in_at_utc')->first();
    }

    public function isClockedIn(): bool
    {
        return $this->currentPunch() !== null;
    }

    public function onboardingTasks(): HasMany
    {
        return $this->hasMany(EmployeeTask::class)->where('kind', 'onboarding');
    }

    public function offboardingTasks(): HasMany
    {
        return $this->hasMany(EmployeeTask::class)->where('kind', 'offboarding');
    }

    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class);
    }

    public function trainingRecords(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }

    /** Derived, per spec B2 — no explicit "rehire" event is modeled in this prototype, so presence of any termination record is the honest current scope (see PLAN.md). */
    public function isTerminated(): bool
    {
        return $this->terminations()->exists();
    }

    /**
     * Spec A6: GDPR purge — anonymizes PII while deliberately preserving the
     * Employee ID's "used" status so it's never reissued (spec B2). Wipes
     * every direct PII column plus the child PII tables (contacts,
     * dependents, immigration, compensation banking/amount, qualifications'
     * free-text fields), but leaves `employee_id`, dates, and FK-based
     * structural fields (job title, sub-unit, location) alone — those
     * aren't personal data and later reports/history still need them.
     * `is_gdpr_purged`/`gdpr_purged_at` are intentionally NOT in $fillable —
     * they must only ever be set here, never via ordinary mass-assignment
     * from a form.
     */
    public function gdprPurge(): void
    {
        $this->emergencyContacts()->delete();
        $this->dependents()->delete();
        $this->immigrationRecords()->delete();
        $this->compensations()->delete();
        $this->education()->delete();
        $this->skills()->delete();
        $this->languages()->delete();
        $this->licenses()->delete();
        $this->memberships()->delete();
        $this->workExperience()->delete();
        $this->clearMediaCollection('avatar');
        $this->clearMediaCollection('documents');

        $redactedFields = [
            'date_of_birth', 'gender', 'marital_status',
            'nationality_id', 'government_id_type', 'government_id_number', 'driving_license_number',
            'home_address', 'home_city_state', 'home_country_id', 'phone_home', 'phone_mobile',
            'personal_email', 'work_email',
        ];

        $this->forceFill(array_merge(
            array_fill_keys($redactedFields, null),
            [
                'first_name' => 'Redacted',
                'last_name' => 'Redacted',
                'is_gdpr_purged' => true,
                'gdpr_purged_at' => now(),
            ]
        ))->save();

        // Erasing the live row isn't enough for a genuine right-to-erasure
        // purge — every prior audit_logs entry for this employee still holds
        // these fields in plain text (that's the audit trail's whole point,
        // for an ordinary edit), including the "updated" row the forceFill()
        // above just wrote. Found during a security review: the purge
        // appeared to erase PII while it actually persisted indefinitely in
        // an Admin-viewable log. See AuditLog::redactHistoryFor()'s own doc
        // comment for why this isn't done inline here.
        AuditLog::redactHistoryFor(self::class, $this->id, array_merge($redactedFields, ['first_name', 'last_name']));
    }

    public function documentsUrl(): array
    {
        return $this->getMedia('documents')->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'url' => route('private-media.show', $m),
            'tab' => $m->getCustomProperty('tab'),
            'description' => $m->getCustomProperty('description'),
        ])->all();
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

    public function jobTitleName(): ?string
    {
        return $this->jobTitle?->name;
    }

    public function departmentName(): ?string
    {
        return $this->subUnit?->name;
    }

    public function locationName(): ?string
    {
        return $this->location?->name;
    }

    public function nationalityName(): ?string
    {
        return $this->nationality?->name;
    }

    public function employmentStatusName(): ?string
    {
        return $this->employmentStatus?->name;
    }

    public function jobCategoryName(): ?string
    {
        return $this->jobCategory?->name;
    }

    public function avatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }

    /** Balance as of today: entitled minus taken/scheduled, per leave type, for the current leave year. Spec C1 — always computed on demand, never cached. Delegates to LeaveBalanceService, which reads the entitlement-consumption ledger rather than summing LeaveRequest.days directly. */
    public function leaveBalance(LeaveType $type, ?int $year = null): array
    {
        return app(LeaveBalanceService::class)->balance($this, $type, $year);
    }
}
