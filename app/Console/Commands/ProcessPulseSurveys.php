<?php

namespace App\Console\Commands;

use App\Models\PulseSurveyRun;
use App\Services\PulseSurveyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Spec F8: "a run is either one-off or on a recurring schedule...launched
 * automatically by a scheduled job, which also handles reminder pushes...
 * partway through the open window." One daily job covers all three
 * transitions: scheduled -> open, a one-time reminder at the midpoint of
 * the open window, and open -> closed (spawning the next recurring run).
 */
class ProcessPulseSurveys extends Command
{
    protected $signature = 'pulse-surveys:process';

    protected $description = 'Open due runs, send midpoint reminders, and close/spawn-recurring runs whose window has ended.';

    public function handle(PulseSurveyService $pulseSurveys): int
    {
        $today = Carbon::today();

        PulseSurveyRun::where('status', 'scheduled')
            ->whereDate('launch_date', '<=', $today)
            ->get()
            ->each(function (PulseSurveyRun $run) {
                $run->update(['status' => 'open']);
            });

        PulseSurveyRun::where('status', 'open')
            ->get()
            ->each(function (PulseSurveyRun $run) use ($pulseSurveys, $today) {
                $midpoint = $run->launch_date->copy()->addDays((int) ($run->launch_date->diffInDays($run->close_date) / 2));
                if ($today->isSameDay($midpoint)) {
                    $pulseSurveys->sendReminders($run);
                }
            });

        PulseSurveyRun::where('status', 'open')
            ->whereDate('close_date', '<', $today)
            ->get()
            ->each(fn (PulseSurveyRun $run) => $pulseSurveys->closeRunAndSpawnNext($run));

        $this->info('Pulse survey processing complete.');

        return self::SUCCESS;
    }
}
