<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Spec's Non-Functional Requirements: "a data-retention policy for audit
 * logs, login history, and notification logs." Archive-then-delete rather
 * than a bare delete — a row's full JSON is written to a dated file on the
 * private disk before it's removed from the live table, so old records
 * remain recoverable (e.g. for a records-retention/legal request) without
 * bloating the primary database indefinitely.
 *
 * The exact retention windows are an Admin-configurable setting, not a
 * fixed policy — this build doesn't have legal/compliance sign-off on what
 * those windows should be, so the honest default is null ("keep forever")
 * on every one of them; nothing is purged until an Admin explicitly opts a
 * log type in from the Settings screen.
 */
class RetentionService
{
    /** @return array<string, int> rows archived+purged per log type, for the scheduled command's own output */
    public function purge(): array
    {
        $settings = Setting::current();

        return [
            'audit_logs' => $this->archiveAndPurge(AuditLog::class, 'audit_logs', $settings->audit_log_retention_days),
            'security_events' => $this->archiveAndPurge(SecurityEvent::class, 'security_events', $settings->security_event_retention_days),
            'notifications' => $this->archiveAndPurge(Notification::class, 'notifications', $settings->notification_retention_days),
        ];
    }

    /** @param  class-string<Model>  $modelClass */
    private function archiveAndPurge(string $modelClass, string $archiveKey, ?int $retentionDays): int
    {
        if ($retentionDays === null) {
            return 0;
        }

        $cutoff = now()->subDays($retentionDays);
        $rows = $modelClass::where('created_at', '<', $cutoff)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $this->archive($archiveKey, $rows);
        $modelClass::whereIn('id', $rows->pluck('id'))->delete();

        return $rows->count();
    }

    /** One JSON-Lines file per purge run per log type — plain enough to grep/restore from without any dedicated tooling. */
    private function archive(string $archiveKey, Collection $rows): void
    {
        $path = "archives/{$archiveKey}/".now()->format('Y-m-d_His').'.jsonl';
        $lines = $rows->map(fn (Model $row) => json_encode($row->toArray()))->implode(PHP_EOL);

        Storage::disk('local')->put($path, $lines);
    }
}
