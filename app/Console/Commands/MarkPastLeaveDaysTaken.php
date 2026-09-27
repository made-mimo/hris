<?php

namespace App\Console\Commands;

use App\Models\LeaveRequestDay;
use Illuminate\Console\Command;

/** Spec C1: "Automatic day-to-day status progression: approved leave whose date has passed is automatically marked 'taken' without requiring a user action." */
class MarkPastLeaveDaysTaken extends Command
{
    protected $signature = 'leave:mark-past-days-taken';

    protected $description = 'Flip scheduled leave days whose date has passed to taken.';

    public function handle(): int
    {
        $count = LeaveRequestDay::where('status', 'scheduled')
            ->where('date', '<', today())
            ->update(['status' => 'taken']);

        $this->info("Marked {$count} leave day(s) as taken.");

        return self::SUCCESS;
    }
}
