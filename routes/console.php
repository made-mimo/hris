<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec Section 3.2's scheduled-job registry: module-declared cron schedules
// defined once in code, not external crontab entries.
Schedule::command('renewals:sweep')->daily();

// Spec C1's Accrual & Carry-Over Engine — all three run daily and are each
// individually idempotent (see their own command/service doc comments), so
// running them once a day at the same time is both correct and simple.
Schedule::command('leave:grant-new-hire-prorations')->daily();
Schedule::command('leave:run-year-end-carryover')->daily();
Schedule::command('leave:mark-past-days-taken')->daily();

// Spec B2's ad-hoc report scheduling — checked daily; ReportSchedule::isDue()
// gates each schedule's own daily/weekly/monthly frequency off last_run_at.
Schedule::command('reports:run-scheduled')->daily();

// Spec F1's Buzz denormalized-count reconciliation backstop.
Schedule::command('buzz:reconcile-counts')->daily();

// Spec F8's Pulse Survey lifecycle: scheduled -> open, midpoint reminders, open -> closed (+ recurring spawn).
Schedule::command('pulse-surveys:process')->daily();

// Spec F2's nightly HR-metrics snapshot — run early each day so the Dashboard's trend widget always has a same-day point.
Schedule::command('hr-metrics:snapshot')->daily();

// Spec's data-retention NFR — archives then purges audit/security-event/notification rows past their Admin-configured window (a no-op for any type left unset).
Schedule::command('retention:purge-logs')->daily();
