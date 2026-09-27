<?php

namespace App\Console\Commands;

use App\Services\LeaveAccrualEngine;
use Illuminate\Console\Command;

/** Spec C1: run daily so a new-hire's prorated batch is granted the moment they cross a leave type's minimumTenureMonths — see App\Services\LeaveAccrualEngine. */
class GrantNewHireLeaveProrations extends Command
{
    protected $signature = 'leave:grant-new-hire-prorations';

    protected $description = 'Grant a New-Hire Prorated entitlement batch to any employee who just reached a leave type\'s minimum tenure.';

    public function handle(LeaveAccrualEngine $engine): int
    {
        $count = $engine->grantNewHireProrations();
        $this->info("Granted {$count} new-hire prorated batch(es).");

        return self::SUCCESS;
    }
}
