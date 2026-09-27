<?php

use App\Models\Employee;
use App\Models\MasterListItem;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public ?string $homeAddress;

    public ?string $homeCityState;

    public ?int $homeCountryId;

    public ?string $phoneHome;

    public ?string $phoneMobile;

    public ?string $personalEmail;

    public ?string $workEmail;

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->homeAddress = $employee->home_address;
        $this->homeCityState = $employee->home_city_state;
        $this->homeCountryId = $employee->home_country_id ?? MasterListItem::defaultCountryId(MasterListItem::TYPE_COUNTRY);
        $this->phoneHome = $employee->phone_home;
        $this->phoneMobile = $employee->phone_mobile;
        $this->personalEmail = $employee->personal_email;
        $this->workEmail = $employee->work_email;
    }

    public function save(): void
    {
        $data = $this->validate([
            'homeAddress' => ['nullable', 'string', 'max:1000'],
            'homeCityState' => ['nullable', 'string', 'max:255'],
            'homeCountryId' => ['nullable', 'exists:master_list_items,id'],
            'phoneHome' => ['nullable', 'digits_between:1,20'],
            'phoneMobile' => ['nullable', 'digits_between:1,20'],
            'personalEmail' => ['nullable', 'email', 'max:255'],
            'workEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $this->employee->update([
            'home_address' => $data['homeAddress'] ?: null,
            'home_city_state' => $data['homeCityState'] ?: null,
            'home_country_id' => $data['homeCountryId'],
            'phone_home' => $data['phoneHome'] ?: null,
            'phone_mobile' => $data['phoneMobile'] ?: null,
            'personal_email' => $data['personalEmail'] ?: null,
            'work_email' => $data['workEmail'] ?: null,
        ]);

        session()->flash('status', 'Contact details updated.');
    }

    public function with(): array
    {
        return ['countries' => MasterListItem::ofType(MasterListItem::TYPE_COUNTRY)->where('is_active', true)->orderBy('sort_order')->get()];
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
                    <label for="homeCityState" class="mb-1.5 block text-xs font-semibold text-text">City / State</label>
                    <input id="homeCityState" type="text" wire:model="homeCityState" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label for="homeCountryId" class="mb-1.5 block text-xs font-semibold text-text">Country</label>
                    <select id="homeCountryId" wire:model="homeCountryId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— none —</option>
                        @foreach($countries as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="phoneHome" class="mb-1.5 block text-xs font-semibold text-text">Home phone</label>
                    <input id="phoneHome" type="tel" inputmode="numeric" wire:model="phoneHome" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="e.g. 08012345678" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('phoneHome') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="phoneMobile" class="mb-1.5 block text-xs font-semibold text-text">Mobile phone</label>
                    <input id="phoneMobile" type="tel" inputmode="numeric" wire:model="phoneMobile" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="e.g. 08012345678" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('phoneMobile') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="personalEmail" class="mb-1.5 block text-xs font-semibold text-text">Personal email</label>
                    <input id="personalEmail" type="email" wire:model="personalEmail" placeholder="name@example.com" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('personalEmail') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="workEmail" class="mb-1.5 block text-xs font-semibold text-text">Work email</label>
                    <input id="workEmail" type="email" wire:model="workEmail" placeholder="name@example.com" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('workEmail') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Save contact details</button>
        </form>
    </section>
</div>
