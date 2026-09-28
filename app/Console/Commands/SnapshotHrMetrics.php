<?php

namespace App\Console\Commands;

use App\Services\HrMetricsService;
use Illuminate\Console\Command;

/** Spec F2: the nightly HR-metrics snapshot job — "start this job running as early in the phase as possible so meaningful trend history exists by go-live." */
class SnapshotHrMetrics extends Command
{
    protected $signature = 'hr-metrics:snapshot';

    protected $description = 'Capture today\'s HR metrics snapshot (headcount, hires/terminations, turnover, open requisitions, pending leave, average tenure).';

    public function handle(HrMetricsService $hrMetrics): int
    {
        $snapshot = $hrMetrics->captureSnapshot();

        $this->info("HR metrics snapshot captured for {$snapshot->snapshot_date->toDateString()}.");

        return self::SUCCESS;
    }
}
