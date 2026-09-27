<?php

namespace App\Console\Commands;

use App\Services\LeaveAccrualEngine;
use Illuminate\Console\Command;

/** Spec C1's year-end carryover job. Runs daily but is a no-op except right after the calendar turns over — see App\Services\LeaveAccrualEngine::runYearEndCarryover()'s own idempotency guard. */
class RunLeaveYearEndCarryover extends Command
{
    protected $signature = 'leave:run-year-end-carryover';

    protected $description = 'Carry over unused carryable leave into Q1 (capped) and grant the new year\'s Standard Grant batches.';

    public function handle(LeaveAccrualEngine $engine): int
    {
        $count = $engine->runYearEndCarryover();
        $this->info("Processed {$count} employee/type carryover(s).");

        return self::SUCCESS;
    }
}
