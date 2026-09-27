<?php

namespace App\Services;

use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Spec B2's ad-hoc employee report builder: field/filter selection, the
 * underlying query, and both export formats live here (not on the Livewire
 * component) so the scheduled-report console command can reuse exactly the
 * same logic the on-demand "Export CSV/PDF" buttons use — one definition of
 * what a report with a given config produces, whether triggered by a click
 * or by cron.
 */
class EmployeeReportService
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

    public function query(array $filters): Builder
    {
        return Employee::query()
            ->with(['jobTitle', 'subUnit', 'location'])
            ->when($filters['jobTitleId'] ?? null, fn ($q, $v) => $q->where('job_title_id', $v))
            ->when($filters['subUnitId'] ?? null, fn ($q, $v) => $q->where('sub_unit_id', $v))
            ->when($filters['locationId'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when(($filters['statusFilter'] ?? 'current') === 'current', fn ($q) => $q->whereDoesntHave('terminations'))
            ->when(($filters['statusFilter'] ?? null) === 'past', fn ($q) => $q->whereHas('terminations'))
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

    public function toCsv(Collection $rows, array $fields): string
    {
        $out = fopen('php://temp', 'w+');
        fputcsv($out, array_map(fn ($f) => self::AVAILABLE_FIELDS[$f], $fields));
        foreach ($rows as $employee) {
            fputcsv($out, array_map(fn ($f) => $this->rowValue($employee, $f), $fields));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public function toPdf(Collection $rows, array $fields, string $title = 'Employee Report'): string
    {
        $html = view('reports.employee-report-pdf', [
            'title' => $title,
            'fields' => $fields,
            'labels' => self::AVAILABLE_FIELDS,
            'rows' => $rows,
            'service' => $this,
        ])->render();

        return Pdf::loadHTML($html)->output();
    }
}
