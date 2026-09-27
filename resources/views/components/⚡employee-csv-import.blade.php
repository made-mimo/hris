<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\SubUnit;
use App\Services\EmployeeIdGenerator;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Spec B2: "CSV bulk import with a downloadable sample template." Columns: first_name, last_name, hire_date, job_title, sub_unit, location — the three master-data columns are resolved by name via firstOrCreate, same pattern the original string-to-FK migration used (see PLAN.md Section 9), so a new value in the CSV becomes a new master row rather than a rejected import. */
new class extends Component
{
    use WithFileUploads;

    public $file = null;

    public bool $showResults = false;

    public int $created = 0;

    public array $rowErrors = [];

    public function import(EmployeeIdGenerator $generator): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $rows = array_map('str_getcsv', file($this->file->getRealPath()));
        $header = array_map('trim', array_shift($rows));

        $this->created = 0;
        $this->rowErrors = [];

        foreach ($rows as $i => $row) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), null));
            $lineNo = $i + 2;

            if (empty($data['first_name']) || empty($data['last_name']) || empty($data['hire_date'])) {
                $this->rowErrors[] = "Row {$lineNo}: first_name, last_name, and hire_date are required.";

                continue;
            }

            try {
                $hireDate = Carbon::parse($data['hire_date']);
            } catch (\Throwable) {
                $this->rowErrors[] = "Row {$lineNo}: invalid hire_date \"{$data['hire_date']}\".";

                continue;
            }

            $jobTitleId = ! empty($data['job_title']) ? JobTitle::firstOrCreate(['name' => trim($data['job_title'])])->id : null;
            $subUnitId = ! empty($data['sub_unit']) ? SubUnit::firstOrCreate(['name' => trim($data['sub_unit'])])->id : null;
            $locationId = ! empty($data['location']) ? Location::firstOrCreate(['name' => trim($data['location'])])->id : null;

            Employee::create([
                'employee_id' => $generator->generate($hireDate),
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'initials' => mb_strtoupper(mb_substr($data['first_name'], 0, 1).mb_substr($data['last_name'], 0, 1)),
                'job_title_id' => $jobTitleId,
                'sub_unit_id' => $subUnitId,
                'location_id' => $locationId,
                'hire_date' => $hireDate,
            ]);

            $this->created++;
        }

        $this->showResults = true;
        $this->reset('file');
    }
};
?>

<div class="rounded-md border border-border bg-surface p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="font-display text-base font-bold text-text">Bulk import employees (CSV)</h2>
        <a href="{{ route('employees.csv-template') }}" class="text-xs font-semibold text-primary">Download sample template</a>
    </div>

    @if($showResults)
        <div class="mb-3 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ $created }} employee(s) created.</div>
        @if(! empty($rowErrors))
            <div class="mb-3 rounded-sm border border-danger/30 bg-danger/5 p-3 text-xs text-danger">
                @foreach($rowErrors as $err)<div>{{ $err }}</div>@endforeach
            </div>
        @endif
    @endif

    <form wire:submit="import" class="flex items-end gap-3">
        <input type="file" wire:model="file" accept=".csv,.txt" class="text-sm text-text">
        <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Import</button>
    </form>
    @error('file') <div class="mt-2 text-xs text-danger">{{ $message }}</div> @enderror
    <div class="mt-2 text-xs text-text-muted">Columns: first_name, last_name, hire_date (YYYY-MM-DD), job_title, sub_unit, location. Only the first three are required — an unrecognized job title/department/location becomes a new master row.</div>
</div>
