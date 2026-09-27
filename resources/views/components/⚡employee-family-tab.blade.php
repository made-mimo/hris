<?php

use App\Models\Employee;
use App\Models\EmployeeDependent;
use App\Models\EmployeeEmergencyContact;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public string $contactName = '';

    public string $contactRelationship = '';

    public string $contactPhone = '';

    public string $contactEmail = '';

    public bool $contactIsPrimary = false;

    public string $dependentName = '';

    public string $dependentRelationship = '';

    public string $dependentDob = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function addContact(): void
    {
        $this->validate([
            'contactName' => ['required', 'string', 'max:150'],
            'contactRelationship' => ['required', 'string', 'max:100'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
        ]);

        if ($this->contactIsPrimary) {
            $this->employee->emergencyContacts()->update(['is_primary' => false]);
        }

        $this->employee->emergencyContacts()->create([
            'name' => $this->contactName,
            'relationship' => $this->contactRelationship,
            'phone' => $this->contactPhone ?: null,
            'email' => $this->contactEmail ?: null,
            'is_primary' => $this->contactIsPrimary,
        ]);

        $this->reset('contactName', 'contactRelationship', 'contactPhone', 'contactEmail', 'contactIsPrimary');
        session()->flash('status', 'Emergency contact added.');
    }

    public function deleteContact(int $id): void
    {
        EmployeeEmergencyContact::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
        session()->flash('status', 'Emergency contact removed.');
    }

    public function addDependent(): void
    {
        $this->validate([
            'dependentName' => ['required', 'string', 'max:150'],
            'dependentRelationship' => ['required', 'string', 'max:100'],
            'dependentDob' => ['nullable', 'date'],
        ]);

        $this->employee->dependents()->create([
            'name' => $this->dependentName,
            'relationship' => $this->dependentRelationship,
            'date_of_birth' => $this->dependentDob ?: null,
        ]);

        $this->reset('dependentName', 'dependentRelationship', 'dependentDob');
        session()->flash('status', 'Dependent added.');
    }

    public function deleteDependent(int $id): void
    {
        EmployeeDependent::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
        session()->flash('status', 'Dependent removed.');
    }

    public function with(): array
    {
        return [
            'contacts' => $this->employee->emergencyContacts()->orderByDesc('is_primary')->get(),
            'dependents' => $this->employee->dependents()->orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Emergency contacts</h2>
        <form wire:submit="addContact" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5 md:items-end">
            <input type="text" wire:model="contactName" placeholder="Name" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="contactRelationship" placeholder="Relationship" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="contactPhone" placeholder="Phone" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="email" wire:model="contactEmail" placeholder="Email" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <div class="flex items-center gap-2">
                <label class="flex items-center gap-1.5 text-xs text-text"><input type="checkbox" wire:model="contactIsPrimary" class="h-4 w-4 accent-primary"> Primary</label>
                <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
            </div>
        </form>
        @error('contactName') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('contactRelationship') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="divide-y divide-border">
            @foreach($contacts as $c)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="font-medium text-text">{{ $c->name }}</span>
                        <span class="text-text-muted">{{ $c->relationship }}</span>
                        @if($c->phone) <span class="text-text-muted">· {{ $c->phone }}</span> @endif
                        @if($c->is_primary) <span class="rounded-pill bg-accent-light px-2 py-0.5 text-[10px] font-semibold text-accent">Primary</span> @endif
                    </div>
                    <button wire:click="deleteContact({{ $c->id }})" wire:confirm="Remove this emergency contact?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($contacts->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No emergency contacts yet.</div>
            @endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Dependents</h2>
        <form wire:submit="addDependent" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 md:items-end">
            <input type="text" wire:model="dependentName" placeholder="Name" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="dependentRelationship" placeholder="Relationship" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="dependentDob" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="self-start rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('dependentName') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('dependentRelationship') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="divide-y divide-border">
            @foreach($dependents as $d)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="font-medium text-text">{{ $d->name }}</span>
                        <span class="text-text-muted">{{ $d->relationship }}</span>
                        @if($d->date_of_birth) <span class="text-text-muted">· born {{ $d->date_of_birth->format('j M Y') }}</span> @endif
                    </div>
                    <button wire:click="deleteDependent({{ $d->id }})" wire:confirm="Remove this dependent?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($dependents->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No dependents yet.</div>
            @endif
        </div>
    </section>
</div>
