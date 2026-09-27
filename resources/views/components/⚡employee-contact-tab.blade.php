<?php

use App\Models\Employee;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public ?string $homeAddress;

    public ?string $phoneHome;

    public ?string $phoneMobile;

    public ?string $personalEmail;

    public ?string $workEmail;

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->homeAddress = $employee->home_address;
        $this->phoneHome = $employee->phone_home;
        $this->phoneMobile = $employee->phone_mobile;
        $this->personalEmail = $employee->personal_email;
        $this->workEmail = $employee->work_email;
    }

    public function save(): void
    {
        $data = $this->validate([
            'homeAddress' => ['nullable', 'string', 'max:1000'],
            'phoneHome' => ['nullable', 'string', 'max:50'],
            'phoneMobile' => ['nullable', 'string', 'max:50'],
            'personalEmail' => ['nullable', 'email', 'max:255'],
            'workEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $this->employee->update([
            'home_address' => $data['homeAddress'] ?: null,
            'phone_home' => $data['phoneHome'] ?: null,
            'phone_mobile' => $data['phoneMobile'] ?: null,
            'personal_email' => $data['personalEmail'] ?: null,
            'work_email' => $data['workEmail'] ?: null,
        ]);

        session()->flash('status', 'Contact details updated.');
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="save" class="flex flex-col gap-3.5">
            <div>
                <label for="homeAddress" class="mb-1.5 block text-xs font-semibold text-text">Home address</label>
                <textarea id="homeAddress" wire:model="homeAddress" rows="2" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                @error('homeAddress') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="phoneHome" class="mb-1.5 block text-xs font-semibold text-text">Home phone</label>
                    <input id="phoneHome" type="text" wire:model="phoneHome" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label for="phoneMobile" class="mb-1.5 block text-xs font-semibold text-text">Mobile phone</label>
                    <input id="phoneMobile" type="text" wire:model="phoneMobile" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="personalEmail" class="mb-1.5 block text-xs font-semibold text-text">Personal email</label>
                    <input id="personalEmail" type="email" wire:model="personalEmail" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('personalEmail') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="workEmail" class="mb-1.5 block text-xs font-semibold text-text">Work email</label>
                    <input id="workEmail" type="email" wire:model="workEmail" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('workEmail') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Save contact details</button>
        </form>
    </section>
</div>
