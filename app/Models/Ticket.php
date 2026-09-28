<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec F4: "Status lifecycle: Open → In Progress → Resolved/Closed, with resolved-date bookkeeping stamped once and cleared if reopened." */
class Ticket extends Model
{
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    protected $fillable = ['subject', 'description', 'helpdesk_category_id', 'status', 'raised_by_id', 'assigned_to_id', 'resolved_at', 'is_anonymous'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'is_anonymous' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'helpdesk_category_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'raised_by_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    /** Spec F4: "the submitter's identity is masked from anyone viewing the ticket (raiser shown as 'Anonymous')" — the raw name, never returned here; callers use App\Services\TicketService::revealIdentity() for the deliberate, audited exception. */
    public function raiserLabel(): string
    {
        return $this->is_anonymous ? 'Anonymous' : $this->raisedBy->fullName();
    }
}
