<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\SubUnit;
use App\Services\EmployeeIdGenerator;
use Livewire\Component;

/**
 * Spec B2: "minimal-friction creation... followed by a tabbed profile
 * editor" — this is the first tab (core job/identity basics); personal,
 * contact, emergency-contact, immigration, compensation, qualifications,
 * multi-supervisor reporting, termination, and attachments tabs are
 * follow-up work (see PLAN.md).
 */
new class extends Component
{
    public Employee $employee;

    public string $firstName;

    public string $lastName;

    public ?int $jobTitleId;

    public ?int $subUnitId;

    public ?int $locationId;

    public string $hireDate;

    public ?int $supervisorId;

    public bool $overridingId = false;

    public string $employeeIdOverride = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->firstName = $employee->first_name;
        $this->lastName = $employee->last_name;
        $this->jobTitleId = $employee->job_title_id;
        $this->subUnitId = $employee->sub_unit_id;
        $this->locationId = $employee->location_id;
        $this->hireDate = $employee->hire_date->toDateString();
        $this->supervisorId = $employee->supervisor_id;
    }

    public function save(): void
    {
        $data = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'jobTitleId' => ['nullable', 'exists:job_titles,id'],
            'subUnitId' => ['nullable', 'exists:sub_units,id'],
            'locationId' => ['nullable', 'exists:locations,id'],
            'hireDate' => ['required', 'date'],
            'supervisorId' => ['nullable', 'exists:employees,id'],
        ]);

        if ($data['supervisorId'] === $this->employee->id) {
            $this->addError('supervisorId', 'An employee cannot be their own supervisor.');

            return;
        }

        $this->employee->update([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'job_title_id' => $data['jobTitleId'],
            'sub_unit_id' => $data['subUnitId'],
            'location_id' => $data['locationId'],
            'hire_date' => $data['hireDate'],
            'supervisor_id' => $data['supervisorId'],
        ]);

        session()->flash('status', 'Employee details updated.');
    }

    /** Spec B2: "Admin/HR Admin override... but only ever to a value that passes the same uniqueness check as an auto-generated one." */
    public function overrideEmployeeId(EmployeeIdGenerator $generator): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isHr(), 403);

        $this->validate(['employeeIdOverride' => ['required', 'string', 'max:50']]);

        if (! $generator->isAvailable($this->employeeIdOverride, $this->employee->id)) {
            $this->addError('employeeIdOverride', 'That employee ID is already in use.');

            return;
        }

        $this->employee->update(['employee_id' => $this->employeeIdOverride]);
        $this->overridingId = false;
        $this->employeeIdOverride = '';
        session()->flash('status', 'Employee ID updated.');
    }

    public function with(): array
    {
        return [
            'supervisors' => Employee::where('id', '!=', $this->employee->id)->orderBy('last_name')->get(),
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'canOverrideId' => auth()->user()->isAdmin() || auth()->user()->isHr(),
        ];
    }
};
?>

<div class="grid grid-2" style="align-items:start;">
    <section class="card">
        @if(session('status'))
            <div class="pill pill-success" style="margin-bottom:16px;padding:10px 14px;">{{ session('status') }}</div>
        @endif

        <div class="card-header"><h2>Job details</h2></div>
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:16px;">
            <div class="grid grid-2">
                <div class="field" style="margin:0;">
                    <label for="firstName">First name</label>
                    <input id="firstName" type="text" wire:model="firstName">
                    @error('firstName') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
                </div>
                <div class="field" style="margin:0;">
                    <label for="lastName">Last name</label>
                    <input id="lastName" type="text" wire:model="lastName">
                    @error('lastName') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field" style="margin:0;">
                    <label for="jobTitleId">Job title</label>
                    <select id="jobTitleId" wire:model="jobTitleId">
                        <option value="">— none —</option>
                        @foreach($jobTitles as $jt)
                            <option value="{{ $jt->id }}">{{ $jt->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0;">
                    <label for="subUnitId">Department</label>
                    <select id="subUnitId" wire:model="subUnitId">
                        <option value="">— none —</option>
                        @foreach($subUnits as $su)
                            <option value="{{ $su->id }}">{{ $su->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field" style="margin:0;">
                    <label for="locationId">Location</label>
                    <select id="locationId" wire:model="locationId">
                        <option value="">— none —</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0;">
                    <label for="hireDate">Hire date</label>
                    <input id="hireDate" type="date" wire:model="hireDate">
                    @error('hireDate') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field" style="margin:0;">
                <label for="supervisorId">Supervisor</label>
                <select id="supervisorId" wire:model="supervisorId">
                    <option value="">— none —</option>
                    @foreach($supervisors as $s)
                        <option value="{{ $s->id }}">{{ $s->fullName() }}</option>
                    @endforeach
                </select>
                @error('supervisorId') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
            </div>

            <div><button type="submit" class="btn btn-primary btn-sm">Save changes</button></div>
        </form>
    </section>

    <section class="card">
        <div class="card-header"><h2>Employee ID</h2></div>
        <div style="font-family:'Courier New',monospace;font-size:18px;font-weight:700;margin-bottom:10px;">{{ $employee->employee_id }}</div>
        <div class="hint" style="margin-bottom:14px;">Permanent and never reassigned, even after termination or a GDPR purge (spec Section B2/A6) — retired for good, not reused.</div>

        @if($canOverrideId)
            @if(! $overridingId)
                <button type="button" wire:click="$set('overridingId', true)" class="btn btn-outline btn-sm">Override ID</button>
            @else
                <div style="display:flex;gap:8px;align-items:flex-start;">
                    <div style="flex:1;">
                        <input type="text" wire:model="employeeIdOverride" placeholder="New employee ID" style="width:100%;padding:8px 10px;border:1px solid var(--color-border);border-radius:8px;font-family:'Courier New',monospace;">
                        @error('employeeIdOverride') <div class="hint" style="color:var(--color-danger);margin-top:4px;">{{ $message }}</div> @enderror
                    </div>
                    <button type="button" wire:click="overrideEmployeeId" class="btn btn-primary btn-sm">Save</button>
                    <button type="button" wire:click="$set('overridingId', false)" class="btn btn-outline btn-sm">Cancel</button>
                </div>
            @endif
        @endif
    </section>
</div>
