<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\SubUnit;
use Livewire\Component;

/**
 * Spec B2: "Ad-hoc and predefined reporting: a configurable report builder
 * (choose display fields, filters, grouping) against employee data,
 * exportable (CSV/PDF) and schedulable for recurring email delivery to a
 * configurable recipient list." This builds the field/filter picker and CSV
 * export; PDF export and the scheduled-email-delivery half aren't built in
 * this prototype slice (see PLAN.md) — CSV covers the "get the data out"
 * need and the scheduling half needs a recipient-list concept this
 * prototype doesn't have anywhere else yet.
 */
new class extends Component
{
    public const AVAILABLE_FIELDS = [
        'employee_id' => 'Employee ID',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'job_title' => 'Job title',
        'sub_unit' => 'Department',
        'location' => 'Location',
        'hire_date' => 'Hire date',
        'work_email' => 'Work email',
        'phone_mobile' => 'Mobile phone',
    ];

    public array $selectedFields = ['employee_id', 'first_name', 'last_name', 'job_title', 'sub_unit', 'hire_date'];

    public ?int $jobTitleId = null;

    public ?int $subUnitId = null;

    public ?int $locationId = null;

    public string $statusFilter = 'current';

    protected function query()
    {
        return Employee::query()
            ->with(['jobTitle', 'subUnit', 'location'])
            ->when($this->jobTitleId, fn ($q) => $q->where('job_title_id', $this->jobTitleId))
            ->when($this->subUnitId, fn ($q) => $q->where('sub_unit_id', $this->subUnitId))
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->when($this->statusFilter === 'current', fn ($q) => $q->whereDoesntHave('terminations'))
            ->when($this->statusFilter === 'past', fn ($q) => $q->whereHas('terminations'))
            ->orderBy('last_name');
    }

    public function rowValue(Employee $employee, string $field): string
    {
        return match ($field) {
            'job_title' => $employee->jobTitleName() ?? '',
            'sub_unit' => $employee->departmentName() ?? '',
            'location' => $employee->locationName() ?? '',
            'hire_date' => $employee->hire_date->toDateString(),
            default => (string) ($employee->{$field} ?? ''),
        };
    }

    public function exportCsv()
    {
        $fields = $this->selectedFields;
        $rows = $this->query()->get();

        return response()->streamDownload(function () use ($rows, $fields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(fn ($f) => self::AVAILABLE_FIELDS[$f], $fields));
            foreach ($rows as $employee) {
                fputcsv($out, array_map(fn ($f) => $this->rowValue($employee, $f), $fields));
            }
            fclose($out);
        }, 'employee-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function with(): array
    {
        return [
            'preview' => $this->query()->limit(20)->get(),
            'totalCount' => $this->query()->count(),
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Fields</h2>
        <div class="mb-4 flex flex-wrap gap-3">
            @foreach(self::AVAILABLE_FIELDS as $key => $label)
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
            <button wire:click="exportCsv" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Export CSV</button>
        </div>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    @foreach($selectedFields as $field)
                        <th class="px-4 py-2.5">{{ self::AVAILABLE_FIELDS[$field] }}</th>
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
</div>
