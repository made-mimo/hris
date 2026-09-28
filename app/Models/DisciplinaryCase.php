<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Spec D3: "Open (query raised) → Responded (employee replied) → Resolved
 * (HR closes it), with an HR-only Follow-up action able to re-open a
 * resolved thread." Visibility restricted to HR Admin and above — the same
 * tier as compensation data.
 */
class DisciplinaryCase extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    public const SEVERITIES = ['low', 'medium', 'high'];

    public const OUTCOMES = ['no_action', 'verbal_warning', 'written_warning', 'suspension', 'termination_recommendation'];

    /** Spec D3: "resolving a case with an outcome of Written Warning or above routes through the E-Signature & Digital Consent Service." */
    public const OUTCOMES_REQUIRING_SIGNATURE = ['written_warning', 'suspension', 'termination_recommendation'];

    protected $fillable = [
        'employee_id', 'raised_by', 'case_type', 'severity', 'description',
        'incident_date', 'status', 'outcome', 'resolution_note',
    ];

    protected function casts(): array
    {
        return ['incident_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('local');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'raised_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CaseResponse::class)->oldest();
    }

    public function outcomeRequiresSignature(): bool
    {
        return in_array($this->outcome, self::OUTCOMES_REQUIRING_SIGNATURE, true);
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome ? ucfirst(str_replace('_', ' ', $this->outcome)) : null;
    }
}
