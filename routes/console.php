<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec Section 3.2's scheduled-job registry: module-declared cron schedules
// defined once in code, not external crontab entries. The first (and so
// far only) real entry — see App\Console\Commands\SweepRenewals.
Schedule::command('renewals:sweep')->daily();
