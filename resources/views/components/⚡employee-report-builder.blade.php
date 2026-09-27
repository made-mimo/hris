<?php

use App\Models\JobTitle;
use App\Models\Location;
use App\Models\ReportSchedule;
use App\Models\SubUnit;
use App\Services\EmployeeReportService;
use Livewire\Component;

/**
 * Spec B2: "Ad-hoc and predefined reporting: a configurable report builder
 * (choose display fields, filters, grouping) against employee data,
 * exportable (CSV/PDF) and schedulable for recurring email delivery to a
 * configurable recipient list." Field/query/export logic lives in
 * EmployeeReportService so the scheduled-report console command
 * (RunScheduledReports) produces byte-identical output to these on-demand
 * buttons.
 */
new class extends Component
{
    public array $selectedFields = ['employee_id', 'first_name', 'last_name', 'job_title', 'sub_unit', 'hire_date'];

    public ?int $jobTitleId = null;

    public ?int $subUnitId = null;

    public ?int $locationId = null;

    public string $statusFilter = 'current';

    public string $scheduleName = '';

    public string $scheduleFrequency = 'weekly';

    public string $scheduleFormat = 'csv';

    public string $scheduleRecipients = '';

    protected function filters(): array
    {
        return [
            'jobTitleId' => $this->jobTitleId,
            'subUnitId' => $this->subUnitId,
            'locationId' => $this->locationId,
            'statusFilter' => $this->statusFilter,
        ];
    }

    public function exportCsv(EmployeeReportService $reports)
    {
        $csv = $reports->toCsv($reports->query($this->filters())->get(), $this->selectedFields);

        return response()->streamDownload(fn () => print($csv), 'employee-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(EmployeeReportService $reports)
    {
        $pdf = $reports->toPdf($reports->query($this->filters())->get(), $this->selectedFields);

        return response()->streamDownload(fn () => print($pdf), 'employee-report.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function saveSchedule(): void
    {
        $data = $this->validate([
            'scheduleName' => ['required', 'string', 'max:100'],
            'scheduleFrequency' => ['required', 'in:'.implode(',', ReportSchedule::FREQUENCIES)],
            'scheduleFormat' => ['required', 'in:'.implode(',', ReportSchedule::FORMATS)],
            'scheduleRecipients' => ['required', 'string'],
        ]);

        $recipients = collect(explode(',', $data['scheduleRecipients']))
            ->map(fn ($e) => trim($e))
            ->filter()
            ->values();

        foreach ($recipients as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addError('scheduleRecipients', "\"$email\" isn't a valid email address.");

                return;
            }
        }

        ReportSchedule::create([
            'name' => $data['scheduleName'],
            'report_type' => 'employee',
            'config' => ['selectedFields' => $this->selectedFields, 'filters' => $this->filters()],
            'recipients' => $recipients->all(),
            'format' => $data['scheduleFormat'],
            'frequency' => $data['scheduleFrequency'],
            'created_by' => auth()->id(),
        ]);

        $this->reset('scheduleName', 'scheduleRecipients');
        session()->flash('status', 'Report schedule saved.');
    }

    public function toggleSchedule(int $id): void
    {
        $schedule = ReportSchedule::findOrFail($id);
        $schedule->update(['is_active' => ! $schedule->is_active]);
    }

    public function deleteSchedule(int $id): void
    {
        ReportSchedule::findOrFail($id)->delete();
    }

    public function with(EmployeeReportService $reports): array
    {
        return [
            'availableFields' => EmployeeReportService::AVAILABLE_FIELDS,
            'preview' => $reports->query($this->filters())->limit(20)->get(),
            'totalCount' => $reports->query($this->filters())->count(),
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'schedules' => ReportSchedule::where('report_type', 'employee')->latest()->get(),
        ];
    }

    public function rowValue($employee, string $field): string
    {
        return app(EmployeeReportService::class)->rowValue($employee, $field);
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Fields</h2>
        <div class="mb-4 flex flex-wrap gap-3">
            @foreach($availableFields as $key => $label)
                <label class="flex items-center gap-1.5 text-xs text-text">
                    <input type="checkbox" wire:model.live="selectedFields" value="{{ $key }}" class="h-4 w-4 accent-primary">
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <h2 class="mb-3.5 font-display text-base font-bold text-text">Filters</h2>
        <div class="mb-4 flex flex-wrap gap-3">
            <select wire:model.live="jobTitleId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">All job titles</option>
                @foreach($jobTitles as $jt)<option value="{{ $jt->id }}">{{ $jt->name }}</option>@endforeach
            </select>
            <select wire:model.live="subUnitId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">All departments</option>
                @foreach($subUnits as $su)<option value="{{ $su->id }}">{{ $su->name }}</option>@endforeach
            </select>
            <select wire:model.live="locationId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">All locations</option>
                @foreach($locations as $loc)<option value="{{ $loc->id }}">{{ $loc->name }}</option>@endforeach
            </select>
            <select wire:model.live="statusFilter" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="current">Current</option>
                <option value="past">Past</option>
                <option value="both">Both</option>
            </select>
        </div>

        <div class="flex items-center justify-between">
            <div class="text-xs text-text-muted">{{ $totalCount }} matching employee{{ $totalCount === 1 ? '' : 's' }} — showing first {{ min(20, $totalCount) }} below.</div>
            <div class="flex gap-2">
                <button wire:click="exportCsv" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Export CSV</button>
                <button wire:click="exportPdf" class="rounded-sm border border-border bg-surface px-4 py-2 text-sm font-semibold text-text hover:bg-bg">Export PDF</button>
            </div>
        </div>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    @foreach($selectedFields as $field)
                        <th class="px-4 py-2.5">{{ $availableFields[$field] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($preview as $employee)
                    <tr class="border-b border-border last:border-0">
                        @foreach($selectedFields as $field)
                            <td class="px-4 py-2.5 text-text">{{ $this->rowValue($employee, $field) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @if($preview->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="{{ max(count($selectedFields), 1) }}">No matching employees.</td></tr>
                @endif
            </tbody>
        </table>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Schedule recurring delivery</h2>
        <p class="mb-3.5 text-xs text-text-muted">Emails this report — with the fields and filters currently selected above — to a recipient list on a recurring basis.</p>

        <form wire:submit="saveSchedule" class="mb-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Schedule name</label>
                <input type="text" wire:model="scheduleName" placeholder="e.g. Monthly headcount" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('scheduleName') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Frequency</label>
                <select wire:model="scheduleFrequency" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Format</label>
                <select wire:model="scheduleFormat" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="csv">CSV</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
            <div style="flex:1;min-width:220px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Recipients</label>
                <input type="text" wire:model="scheduleRecipients" placeholder="finance@company.com, hr@company.com" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('scheduleRecipients') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save schedule</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($schedules as $schedule)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <div>
                        <span class="font-semibold text-text">{{ $schedule->name }}</span>
                        <span class="ml-2 text-xs text-text-muted">{{ ucfirst($schedule->frequency) }} · {{ strtoupper($schedule->format) }} · {{ count($schedule->recipients) }} recipient{{ count($schedule->recipients) === 1 ? '' : 's' }}</span>
                        @if(! $schedule->is_active)
                            <span class="ml-2 rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Paused</span>
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <button wire:click="toggleSchedule({{ $schedule->id }})" class="text-xs font-semibold text-primary">{{ $schedule->is_active ? 'Pause' : 'Resume' }}</button>
                        <button wire:click="deleteSchedule({{ $schedule->id }})" wire:confirm="Delete this report schedule?" class="text-xs font-semibold text-danger">Delete</button>
                    </div>
                </div>
            @endforeach
            @if($schedules->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No report schedules yet.</div>
            @endif
        </div>
    </section>
</div>
