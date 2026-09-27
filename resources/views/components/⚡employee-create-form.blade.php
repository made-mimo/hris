<?php

use App\Models\Employee;
use App\Services\EmployeeIdGenerator;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $hireDate;

    public string $jobTitle = '';

    public string $department = '';

    public string $location = '';

    public ?int $supervisorId = null;

    public function mount(): void
    {
        $this->hireDate = now()->toDateString();
    }

    public function save(EmployeeIdGenerator $generator): void
    {
        $data = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'hireDate' => ['required', 'date'],
            'jobTitle' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'supervisorId' => ['nullable', 'exists:employees,id'],
        ]);

        $employee = Employee::create([
            'employee_id' => $generator->generate(\Illuminate\Support\Carbon::parse($data['hireDate'])),
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'initials' => Str::upper(Str::substr($data['firstName'], 0, 1).Str::substr($data['lastName'], 0, 1)),
            'job_title' => $data['jobTitle'] ?: null,
            'department' => $data['department'] ?: null,
            'location' => $data['location'] ?: null,
            'hire_date' => $data['hireDate'],
            'supervisor_id' => $data['supervisorId'],
        ]);

        session()->flash('status', "{$employee->fullName()} added — Employee ID {$employee->employee_id}.");
        $this->redirectRoute('employees.show', $employee, navigate: true);
    }

    public function with(): array
    {
        return ['supervisors' => Employee::orderBy('last_name')->get()];
    }
};
?>

<section class="card" style="max-width:560px;">
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

        <div class="field" style="margin:0;">
            <label for="hireDate">Hire date</label>
            <input id="hireDate" type="date" wire:model="hireDate">
            <div class="hint">Used for the {YY}/{MM} tokens in the Employee ID format — see Settings.</div>
            @error('hireDate') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
        </div>

        <div class="grid grid-2">
            <div class="field" style="margin:0;">
                <label for="jobTitle">Job title <span class="text-muted">(optional)</span></label>
                <input id="jobTitle" type="text" wire:model="jobTitle">
            </div>
            <div class="field" style="margin:0;">
                <label for="department">Department <span class="text-muted">(optional)</span></label>
                <input id="department" type="text" wire:model="department">
            </div>
        </div>

        <div class="grid grid-2">
            <div class="field" style="margin:0;">
                <label for="location">Location <span class="text-muted">(optional)</span></label>
                <input id="location" type="text" wire:model="location">
            </div>
            <div class="field" style="margin:0;">
                <label for="supervisorId">Supervisor <span class="text-muted">(optional)</span></label>
                <select id="supervisorId" wire:model="supervisorId">
                    <option value="">— none —</option>
                    @foreach($supervisors as $s)
                        <option value="{{ $s->id }}">{{ $s->fullName() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Add employee</button>
        </div>
    </form>
</section>
