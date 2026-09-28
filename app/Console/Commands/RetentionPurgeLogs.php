<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

/** Spec's Non-Functional Requirements: "a data-retention policy for audit logs, login history, and notification logs." No-op for any log type whose retention window is unset (the default). */
class RetentionPurgeLogs extends Command
{
    protected $signature = 'retention:purge-logs';

    protected $description = 'Archive (to storage/app/private/archives) then delete audit log, security event, and notification rows past their Admin-configured retention window.';

    public function handle(RetentionService $retention): int
    {
        $results = $retention->purge();

        foreach ($results as $type => $count) {
            $this->info("{$type}: {$count} row(s) archived and purged.");
        }

        return self::SUCCESS;
    }
}
