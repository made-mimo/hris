<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['auditable_type', 'auditable_id', 'action', 'actor_id', 'actor_label', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** A short, human label for the record this row is about, even after it's been deleted. */
    public function subjectLabel(): string
    {
        $short = class_basename($this->auditable_type);

        return "{$short} #{$this->auditable_id}";
    }

    /**
     * Spec A6: a GDPR purge that erases the live record but leaves these
     * same fields in plain text on every prior audit_logs row for it isn't
     * a real erasure — that history is exactly as Admin-viewable as the
     * live record was. Redacts each named field wherever it appears, in
     * both the flat shape ('created'/'deleted' rows) and the [old, new]
     * pair shape ('updated' rows).
     *
     * Reads/writes `changes` via its raw column value (json_decode/
     * json_encode by hand) rather than the model's own `array` cast:
     * while building this, calling it from within Employee::gdprPurge()'s
     * call chain made every `$log->changes` access below silently return an
     * empty array even though getRawOriginal() held the correct JSON the
     * whole time — not reproducible in isolation, and not root-caused, but
     * bypassing the cast entirely sidesteps it regardless of cause.
     */
    public static function redactHistoryFor(string $auditableType, int $auditableId, array $fields): void
    {
        foreach (static::where('auditable_type', $auditableType)->where('auditable_id', $auditableId)->get() as $log) {
            $changes = json_decode($log->getRawOriginal('changes') ?? '[]', true) ?? [];
            $dirty = false;

            foreach ($fields as $field) {
                if (! array_key_exists($field, $changes)) {
                    continue;
                }

                $isBeforeAfterPair = is_array($changes[$field]) && array_is_list($changes[$field]) && count($changes[$field]) === 2;
                $changes[$field] = $isBeforeAfterPair ? ['[redacted]', '[redacted]'] : '[redacted]';
                $dirty = true;
            }

            if ($dirty) {
                static::where('id', $log->id)->update(['changes' => json_encode($changes)]);
            }
        }
    }
}
