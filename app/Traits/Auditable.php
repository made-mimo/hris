<?php

namespace App\Traits;

use App\Models\AuditLog;

/**
 * Spec Section 3.2's Audit Logging platform service: attach this to a model
 * and every create/update/delete is captured automatically — no manual
 * instrumentation in the controllers/components that mutate it. Field-level
 * before/after values are recorded for updates, matching the spec's "field-
 * level before/after values" requirement.
 *
 * A model may define `protected array $auditExcept = [...]` to name
 * additional fields to leave out of the recorded diff (beyond the
 * always-redacted credential-shaped fields below).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', $model->redactedAttributes($model->attributesToArray()));
        });

        static::updated(function ($model) {
            $changes = [];

            // Both sides go through attributesToArray()'s cast pipeline (not
            // getChanges()'s raw pre-cast values) so e.g. a JSON column reads
            // as a clean array on both sides of the diff, not a raw string.
            foreach ($model->getChanges() as $key => $ignored) {
                if (in_array($key, ['updated_at'], true) || $model->isAuditRedacted($key)) {
                    continue;
                }

                $changes[$key] = [$model->getOriginal($key), $model->getAttribute($key)];
            }

            if ($changes !== []) {
                $model->writeAuditLog('updated', $changes);
            }
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', $model->redactedAttributes($model->attributesToArray()));
        });
    }

    protected function writeAuditLog(string $action, array $changes): void
    {
        $actor = auth()->user();

        AuditLog::create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'actor_id' => $actor?->id,
            'actor_label' => $actor?->email,
            'changes' => $changes,
        ]);
    }

    protected function isAuditRedacted(string $key): bool
    {
        $alwaysRedacted = ['password', 'remember_token', 'two_factor_secret', 'code_hash', 'token_hash'];

        return in_array($key, array_merge($alwaysRedacted, $this->auditExcept ?? []), true);
    }

    protected function redactedAttributes(array $attributes): array
    {
        foreach (array_keys($attributes) as $key) {
            if ($this->isAuditRedacted($key)) {
                $attributes[$key] = '[redacted]';
            }
        }

        return $attributes;
    }
}
