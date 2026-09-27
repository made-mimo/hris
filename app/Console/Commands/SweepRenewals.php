<?php

namespace App\Console\Commands;

use App\Services\RenewalReminderEngine;
use Illuminate\Console\Command;

/**
 * Spec Section 3.2's scheduled-job registry: "the Renewal & Compliance
 * Reminder Engine's daily sweep across vehicle renewals/asset warranties/
 * company registration documents." Registered in bootstrap/app.php's
 * ->withSchedule() to run once daily — Laravel's own Task Scheduler
 * directly implementing that registry, per spec Section 3.5.
 */
class SweepRenewals extends Command
{
    protected $signature = 'renewals:sweep';

    protected $description = 'Fire due reminder tiers and persistent expired alerts for every active renewable.';

    public function handle(RenewalReminderEngine $engine): int
    {
        $engine->sweep();

        $this->info('Renewal sweep complete.');

        return self::SUCCESS;
    }
}
