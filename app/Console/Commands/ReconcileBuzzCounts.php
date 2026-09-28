<?php

namespace App\Console\Commands;

use App\Services\BuzzService;
use Illuminate\Console\Command;

/** Spec F1: "a denormalized like/comment count on each share, kept consistent by a periodic reconciliation job as a correctness backstop in addition to inline updates." */
class ReconcileBuzzCounts extends Command
{
    protected $signature = 'buzz:reconcile-counts';

    protected $description = 'Recompute every post share\'s denormalized like/comment counts from source.';

    public function handle(BuzzService $buzz): int
    {
        $buzz->reconcileCounts();

        $this->info('Buzz count reconciliation complete.');

        return self::SUCCESS;
    }
}
