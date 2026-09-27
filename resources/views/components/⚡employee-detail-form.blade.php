<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\MasterListItem;
use App\Models\SubUnit;
use App\Services\EmployeeIdGenerator;
use Livewire\Component;

/**
 * Spec B2: "minimal-friction creation... followed by a tabbed profile
 * editor" — this is the Job Details tab (core job/identity basics + the
 * employment relationship fields); every other tab lives in
 * employee-profile-tabs (see PLAN.md Section 10). Primary supervisor is
 * managed on the Reporting tab now (the real multi-supervisor graph), not
 * here, to avoid two screens racing to set the same `supervisor_id` column.
 */
new class extends Component
{
    public Employee $employee;

    public string $firstName;

    public string $lastName;

    public ?int $jobTitleId;

    public ?int $jobCategoryId;

    public ?int $subUnitId;

    public ?int $locationId;

    public ?int $employmentStatusId;

    public string $hireDate;

    public ?string $contractStartDate;

    public ?string $contractEndDate;

    public bool $overridingId = false;

    public string $employeeIdOverride = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->firstName = $employee->first_name;
        $this->lastName = $employee->last_name;
        $this->jobTitleId = $employee->job_title_id;
        $this->jobCategoryId = $employee->job_category_id;
        $this->subUnitId = $employee->sub_unit_id;
        $this->locationId = $employee->location_id;
        $this->employmentStatusId = $employee->employment_status_id;
        $this->hireDate = $employee->hire_date->toDateString();
        $this->contractStartDate = $employee->contract_start_date?->toDateString();
        $this->contractEndDate = $employee->contract_end_date?->toDateString();
    }

    public function save(): void
    {
        $data = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'jobTitleId' => ['nullable', 'exists:job_titles,id'],
            'jobCategoryId' => ['nullable', 'exists:master_list_items,id'],
            'subUnitId' => ['nullable', 'exists:sub_units,id'],
            'locationId' => ['nullable', 'exists:locations,id'],
            'employmentStatusId' => ['nullable', 'exists:master_list_items,id'],
            'hireDate' => ['required', 'date'],
            'contractStartDate' => ['nullable', 'date'],
            'contractEndDate' => ['nullable', 'date', 'after_or_equal:contractStartDate'],
        ]);

        $this->employee->update([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'job_title_id' => $data['jobTitleId'],
            'job_category_id' => $data['jobCategoryId'],
            'sub_unit_id' => $data['subUnitId'],
            'location_id' => $data['locationId'],
            'employment_status_id' => $data['employmentStatusId'],
            'hire_date' => $data['hireDate'],
            'contract_start_date' => $data['contractStartDate'] ?: null,
            'contract_end_date' => $data['contractEndDate'] ?: null,
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
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
            'jobCategories' => MasterListItem::ofType(MasterListItem::TYPE_JOB_CATEGORY)->where('is_active', true)->orderBy('sort_order')->get(),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'employmentStatuses' => MasterListItem::ofType(MasterListItem::TYPE_EMPLOYMENT_STATUS)->where('is_active', true)->orderBy('sort_order')->get(),
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
                    <label for="jobCategoryId">Job category</label>
                    <select id="jobCategoryId" wire:model="jobCategoryId">
                        <option value="">— none —</option>
                        @foreach($jobCategories as $jc)
                            <option value="{{ $jc->id }}">{{ $jc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field" style="margin:0;">
                    <label for="employmentStatusId">Employment status</label>
                    <select id="employmentStatusId" wire:model="employmentStatusId">
                        <option value="">— none —</option>
                        @foreach($employmentStatuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0;">
                    <label for="hireDate">Hire date</label>
                    <input id="hireDate" type="date" wire:model="hireDate">
                    @error('hireDate') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field" style="margin:0;">
                    <label for="contractStartDate">Contract start</label>
                    <input id="contractStartDate" type="date" wire:model="contractStartDate">
                </div>
                <div class="field" style="margin:0;">
                    <label for="contractEndDate">Contract end</label>
                    <input id="contractEndDate" type="date" wire:model="contractEndDate">
                    @error('contractEndDate') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="hint">Supervisors are managed on the Reporting tab.</div>

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
