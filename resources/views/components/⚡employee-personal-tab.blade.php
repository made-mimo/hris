<?php

use App\Models\Employee;
use App\Models\MasterListItem;
use Livewire\Component;

new class extends Component
{
    public const GENDERS = ['Male', 'Female'];

    public const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];

    public Employee $employee;

    public ?string $dateOfBirth;

    public ?string $gender;

    public ?string $maritalStatus;

    public ?int $nationalityId;

    public ?string $governmentIdType;

    public ?string $governmentIdNumber;

    public ?string $drivingLicenseNumber;

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->dateOfBirth = $employee->date_of_birth?->toDateString();
        $this->gender = $employee->gender;
        $this->maritalStatus = $employee->marital_status;
        $this->nationalityId = $employee->nationality_id ?? MasterListItem::defaultCountryId(MasterListItem::TYPE_NATIONALITY);
        $this->governmentIdType = $employee->government_id_type;
        $this->governmentIdNumber = $employee->government_id_number;
        $this->drivingLicenseNumber = $employee->driving_license_number;
    }

    public function save(): void
    {
        $data = $this->validate([
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:'.implode(',', self::GENDERS)],
            'maritalStatus' => ['nullable', 'string', 'in:'.implode(',', self::MARITAL_STATUSES)],
            'nationalityId' => ['nullable', 'exists:master_list_items,id'],
            'governmentIdType' => ['nullable', 'string', 'max:100'],
            'governmentIdNumber' => ['nullable', 'string', 'max:255'],
            'drivingLicenseNumber' => ['nullable', 'string', 'max:100'],
        ]);

        $this->employee->update([
            'date_of_birth' => $data['dateOfBirth'] ?: null,
            'gender' => $data['gender'] ?: null,
            'marital_status' => $data['maritalStatus'] ?: null,
            'nationality_id' => $data['nationalityId'],
            'government_id_type' => $data['governmentIdType'] ?: null,
            'government_id_number' => $data['governmentIdNumber'] ?: null,
            'driving_license_number' => $data['drivingLicenseNumber'] ?: null,
        ]);

        session()->flash('status', 'Personal details updated.');
    }

    public function with(): array
    {
        return ['nationalities' => MasterListItem::ofType(MasterListItem::TYPE_NATIONALITY)->where('is_active', true)->orderBy('sort_order')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="save" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="dateOfBirth" class="mb-1.5 block text-xs font-semibold text-text">Date of birth</label>
                    <input id="dateOfBirth" type="date" wire:model="dateOfBirth" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('dateOfBirth') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="gender" class="mb-1.5 block text-xs font-semibold text-text">Gender</label>
                    <select id="gender" wire:model="gender" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— none —</option>
                        @foreach(self::GENDERS as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="maritalStatus" class="mb-1.5 block text-xs font-semibold text-text">Marital status</label>
                    <select id="maritalStatus" wire:model="maritalStatus" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— none —</option>
                        @foreach(self::MARITAL_STATUSES as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="nationalityId" class="mb-1.5 block text-xs font-semibold text-text">Nationality</label>
                    <select id="nationalityId" wire:model="nationalityId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— none —</option>
                        @foreach($nationalities as $n)
                            <option value="{{ $n->id }}">{{ $n->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="governmentIdType" class="mb-1.5 block text-xs font-semibold text-text">Government ID type</label>
                    <input id="governmentIdType" type="text" wire:model="governmentIdType" placeholder="e.g. National ID, Passport" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label for="governmentIdNumber" class="mb-1.5 block text-xs font-semibold text-text">Government ID number</label>
                    <input id="governmentIdNumber" type="text" wire:model="governmentIdNumber" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <div class="mt-1 text-xs text-text-muted">Encrypted at rest.</div>
                </div>
            </div>

            <div>
                <label for="drivingLicenseNumber" class="mb-1.5 block text-xs font-semibold text-text">Driving license number</label>
                <input id="drivingLicenseNumber" type="text" wire:model="drivingLicenseNumber" class="w-full max-w-xs rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>

            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Save personal details</button>
        </form>
    </section>
</div>
