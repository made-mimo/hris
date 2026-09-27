<?php

namespace App\Console\Commands;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Services\EmployeeReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Spec B2: "schedulable for recurring email delivery to a configurable recipient list." Runs daily; ReportSchedule::isDue() gates daily/weekly/monthly frequencies off last_run_at. */
class RunScheduledReports extends Command
{
    protected $signature = 'reports:run-scheduled';

    protected $description = 'Email every due, active report schedule its report attachment.';

    public function handle(EmployeeReportService $reports): int
    {
        $due = ReportSchedule::where('is_active', true)->get()->filter->isDue();

        foreach ($due as $schedule) {
            $config = $schedule->config;
            $rows = $reports->query($config['filters'] ?? [])->get();
            $fields = $config['selectedFields'] ?? array_keys(EmployeeReportService::AVAILABLE_FIELDS);

            if ($schedule->format === 'pdf') {
                $contents = $reports->toPdf($rows, $fields, $schedule->name);
                $filename = "{$schedule->name}.pdf";
                $mime = 'application/pdf';
            } else {
                $contents = $reports->toCsv($rows, $fields);
                $filename = "{$schedule->name}.csv";
                $mime = 'text/csv';
            }

            foreach ($schedule->recipients as $recipient) {
                try {
                    Mail::to($recipient)->send(new ScheduledReportMail($schedule, $contents, $filename, $mime));
                } catch (Throwable $e) {
                    Log::warning('Scheduled report email failed to send.', ['schedule_id' => $schedule->id, 'recipient' => $recipient, 'error' => $e->getMessage()]);
                }
            }

            $schedule->update(['last_run_at' => now()]);
        }

        $this->info("Ran {$due->count()} due report schedule(s).");

        return self::SUCCESS;
    }
}
