<?php

use App\Models\CustomFieldValue;
use App\Models\Employee;
use App\Models\SubUnit;
use App\Models\Vehicle;
use App\Services\PermissionService;
use App\Services\VehicleService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Spec E3: full register for Admin/HR Admin/HR Officer; an ordinary ESS user sees only vehicles currently assigned to them (never to their department), plus their own assignment history. */
new class extends Component
{
    public bool $creating = false;

    public string $make = '';

    public string $model = '';

    public string $year = '';

    public string $vin = '';

    public string $engineNumber = '';

    public string $registrationNumber = '';

    public string $color = '';

    public ?int $expandedId = null;

    public ?int $assigningId = null;

    public string $assignTarget = 'employee';

    public ?int $assignEmployeeId = null;

    public ?int $assignSubUnitId = null;

    public bool $licenseWarningAcknowledged = false;

    public string $overrideReason = '';

    public array $licenseWarning = [];

    public ?int $fuelId = null;

    public string $fuelDate = '';

    public string $fuelOdometer = '';

    public string $fuelCost = '';

    public string $fuelNotes = '';

    public ?int $renewalId = null;

    public string $renewalLabel = '';

    public string $renewalProvider = '';

    public string $renewalReferenceNumber = '';

    public string $renewalIssueDate = '';

    public string $renewalExpiryDate = '';

    public string $renewalMileageInterval = '';

    public array $customFieldInputs = [];

    public function create(): void
    {
        $data = $this->validate([
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'vin' => ['required', 'string', 'max:50', 'unique:vehicles,vin'],
            'engineNumber' => ['nullable', 'string', 'max:100'],
            'registrationNumber' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        Vehicle::create([
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'] ?: null,
            'vin' => $data['vin'],
            'engine_number' => $data['engineNumber'] ?: null,
            'registration_number' => $data['registrationNumber'] ?: null,
            'color' => $data['color'] ?: null,
            'status' => 'active',
        ]);

        $this->reset('creating', 'make', 'model', 'year', 'vin', 'engineNumber', 'registrationNumber', 'color');
        session()->flash('status', 'Vehicle added.');
    }

    public function startAssign(int $id): void
    {
        $this->assigningId = $id;
        $this->reset('assignEmployeeId', 'assignSubUnitId', 'licenseWarningAcknowledged', 'overrideReason', 'licenseWarning');
        $this->assignTarget = 'employee';
    }

    public function assign(int $id, VehicleService $vehicles): void
    {
        $vehicle = Vehicle::findOrFail($id);

        if ($this->assignTarget === 'employee') {
            $data = $this->validate(['assignEmployeeId' => ['required', 'exists:employees,id']]);
            $employee = Employee::findOrFail($data['assignEmployeeId']);

            $check = $vehicles->checkDrivingLicense($employee);

            if (! $check['valid'] && ! $this->licenseWarningAcknowledged) {
                $this->licenseWarning = $check;

                return;
            }

            $overrideReason = ! $check['valid'] ? ($this->overrideReason ?: 'Overridden without a reason.') : null;

            $vehicles->assign($vehicle, $employee, null, $overrideReason, $overrideReason ? auth()->user() : null);
        } else {
            $data = $this->validate(['assignSubUnitId' => ['required', 'exists:sub_units,id']]);
            $vehicles->assign($vehicle, null, SubUnit::findOrFail($data['assignSubUnitId']), null, null);
        }

        $this->reset('assigningId', 'assignEmployeeId', 'assignSubUnitId', 'licenseWarningAcknowledged', 'overrideReason', 'licenseWarning');
        session()->flash('status', 'Vehicle assigned.');
    }

    public function unassign(int $id, VehicleService $vehicles): void
    {
        $vehicles->unassign(Vehicle::findOrFail($id));
        session()->flash('status', 'Vehicle unassigned.');
    }

    public function setStatus(int $id, string $status, VehicleService $vehicles): void
    {
        $vehicles->setStatus(Vehicle::findOrFail($id), $status);
        session()->flash('status', 'Status updated.');
    }

    public function logFuel(int $id, VehicleService $vehicles): void
    {
        $data = $this->validate([
            'fuelDate' => ['required', 'date'],
            'fuelOdometer' => ['required', 'integer', 'min:0'],
            'fuelCost' => ['nullable', 'numeric', 'min:0'],
            'fuelNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $vehicles->logFuel(
            Vehicle::findOrFail($id),
            \Illuminate\Support\Carbon::parse($data['fuelDate']),
            (int) $data['fuelOdometer'],
            $data['fuelCost'] ?: null,
            $data['fuelNotes'] ?: null,
        );

        $this->reset('fuelId', 'fuelDate', 'fuelOdometer', 'fuelCost', 'fuelNotes');
        session()->flash('status', 'Fuel/mileage logged.');
    }

    public function addRenewal(int $id, VehicleService $vehicles): void
    {
        $data = $this->validate([
            'renewalLabel' => ['required', 'string', 'max:100'],
            'renewalProvider' => ['nullable', 'string', 'max:150'],
            'renewalReferenceNumber' => ['nullable', 'string', 'max:100'],
            'renewalIssueDate' => ['required', 'date'],
            'renewalExpiryDate' => ['required', 'date', 'after:renewalIssueDate'],
            'renewalMileageInterval' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $vehicles->addRenewal(
                Vehicle::findOrFail($id),
                $data['renewalLabel'],
                $data['renewalProvider'] ?: null,
                $data['renewalReferenceNumber'] ?: null,
                \Illuminate\Support\Carbon::parse($data['renewalIssueDate']),
                \Illuminate\Support\Carbon::parse($data['renewalExpiryDate']),
                null,
                $data['renewalMileageInterval'] ?: null,
            );
        } catch (ValidationException $e) {
            $this->addError('renewalExpiryDate', $e->errors()['expiryDate'][0]);

            return;
        }

        $this->reset('renewalId', 'renewalLabel', 'renewalProvider', 'renewalReferenceNumber', 'renewalIssueDate', 'renewalExpiryDate', 'renewalMileageInterval');
        session()->flash('status', 'Renewal added.');
    }

    public function deleteVehicle(int $id, VehicleService $vehicles): void
    {
        $vehicles->deleteVehicle(Vehicle::findOrFail($id));
        session()->flash('status', 'Vehicle deleted.');
    }

    public function saveCustomField(int $vehicleId, int $definitionId): void
    {
        $value = array_key_exists($definitionId, $this->customFieldInputs)
            ? $this->customFieldInputs[$definitionId]
            : CustomFieldValue::where(['definition_id' => $definitionId, 'valuable_type' => Vehicle::class, 'valuable_id' => $vehicleId])->value('value');

        CustomFieldValue::updateOrCreate(
            ['definition_id' => $definitionId, 'valuable_type' => Vehicle::class, 'valuable_id' => $vehicleId],
            ['value' => $value]
        );

        session()->flash('status', 'Custom field saved.');
    }

    public function with(PermissionService $permissions): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'vehicles');
        $isManager = $scope === 'all';

        $vehicles = Vehicle::with(['currentEmployee', 'currentSubUnit', 'assignmentHistory.employee', 'assignmentHistory.subUnit', 'assignmentHistory.overriddenBy', 'renewals.renewable', 'fuelLogs', 'customFieldValues.definition'])
            ->when(! $isManager, fn ($q) => $q->where('current_employee_id', $me?->id))
            ->latest()
            ->get();

        return [
            'vehicles' => $vehicles,
            'isManager' => $isManager,
            'employees' => Employee::orderBy('last_name')->get(),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get(),
            'customFieldDefs' => \App\Models\CustomFieldDefinition::where('subject_type', 'vehicle')->where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    @if($isManager)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            @if(! $creating)
                <button wire:click="$set('creating', true)" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add vehicle</button>
            @else
                <h2 class="mb-3.5 font-display text-base font-bold text-text">New vehicle</h2>
                <form wire:submit="create" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Make</label>
                        <input type="text" wire:model="make" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @error('make') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Model</label>
                        <input type="text" wire:model="model" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @error('model') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Year</label>
                        <input type="number" wire:model="year" class="w-24 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @error('year') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">VIN</label>
                        <input type="text" wire:model="vin" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @error('vin') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Engine number</label>
                        <input type="text" wire:model="engineNumber" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Registration number</label>
                        <input type="text" wire:model="registrationNumber" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Color</label>
                        <input type="text" wire:model="color" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                    <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
                    <button type="button" wire:click="$set('creating', false)" class="text-sm font-semibold text-text-muted">Cancel</button>
                </form>
            @endif
        </section>
    @endif

    <div class="flex flex-col gap-3">
        @foreach($vehicles as $vehicle)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button wire:click="$set('expandedId', {{ $expandedId === $vehicle->id ? 'null' : $vehicle->id }})" class="text-left">
                        <div class="font-display text-sm font-bold text-text">{{ $vehicle->make }} {{ $vehicle->model }} {{ $vehicle->year }} <span class="font-mono text-xs text-text-muted">{{ $vehicle->vin }}</span></div>
                        <div class="text-xs text-text-muted">{{ $vehicle->currentAssigneeLabel() ?? 'Unassigned' }}</div>
                    </button>
                    <span class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $vehicle->status === 'active' ? 'bg-accent-light text-accent' : ($vehicle->status === 'in_service' ? 'bg-warning-light text-warning' : 'bg-text-faint/15 text-text-muted') }}">{{ ucfirst(str_replace('_', ' ', $vehicle->status)) }}</span>
                </div>

                @if($expandedId === $vehicle->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        @if($isManager)
                            <div class="flex flex-wrap gap-2">
                                @if($vehicle->status !== 'retired')
                                    @if($vehicle->current_employee_id || $vehicle->current_sub_unit_id)
                                        <button wire:click="unassign({{ $vehicle->id }})" class="text-xs font-semibold text-primary">Unassign</button>
                                    @else
                                        <button wire:click="startAssign({{ $vehicle->id }})" class="text-xs font-semibold text-primary">Assign</button>
                                    @endif
                                    @if($vehicle->status === 'active')
                                        <button wire:click="setStatus({{ $vehicle->id }}, 'in_service')" class="text-xs font-semibold text-primary">Mark In Service</button>
                                    @else
                                        <button wire:click="setStatus({{ $vehicle->id }}, 'active')" class="text-xs font-semibold text-primary">Mark Active</button>
                                    @endif
                                    <button wire:click="$set('fuelId', {{ $vehicle->id }})" class="text-xs font-semibold text-primary">Log fuel/mileage</button>
                                    <button wire:click="$set('renewalId', {{ $vehicle->id }})" class="text-xs font-semibold text-primary">Add renewal</button>
                                    <button wire:click="setStatus({{ $vehicle->id }}, 'retired')" wire:confirm="Retire this vehicle?" class="text-xs font-semibold text-danger">Retire</button>
                                @endif
                                <button wire:click="deleteVehicle({{ $vehicle->id }})" wire:confirm="Permanently delete this vehicle and all its renewals, history, and logs? This cannot be undone." class="text-xs font-semibold text-danger">Delete</button>
                            </div>

                            @if($assigningId === $vehicle->id)
                                <div class="rounded-sm border border-border bg-bg p-3.5">
                                    <div class="mb-2 flex gap-3 text-xs">
                                        <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="assignTarget" value="employee" class="accent-primary"> Employee</label>
                                        <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="assignTarget" value="department" class="accent-primary"> Department</label>
                                    </div>

                                    @if($assignTarget === 'employee')
                                        <select wire:model="assignEmployeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                            <option value="">— select employee —</option>
                                            @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                                        </select>
                                        @error('assignEmployeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    @else
                                        <select wire:model="assignSubUnitId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                            <option value="">— select department —</option>
                                            @foreach($subUnits as $su)<option value="{{ $su->id }}">{{ $su->name }}</option>@endforeach
                                        </select>
                                        @error('assignSubUnitId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    @endif

                                    @if(! empty($licenseWarning))
                                        <div class="mt-3 rounded-sm border border-warning/40 bg-warning-light p-3 text-xs text-warning">
                                            <div class="mb-1.5 font-semibold">{{ $licenseWarning['reason'] }}</div>
                                            <div class="mb-2 text-text">Spec E3: this is a warning, not a hard block — you may proceed with an explicit, logged override reason.</div>
                                            <input type="text" wire:model="overrideReason" placeholder="Override reason (required to proceed)" class="w-full rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                            <label class="mt-2 flex items-center gap-1.5 text-xs text-text">
                                                <input type="checkbox" wire:model="licenseWarningAcknowledged" class="h-3.5 w-3.5 accent-warning">
                                                I acknowledge this and want to proceed anyway
                                            </label>
                                        </div>
                                    @endif

                                    <div class="mt-2 flex gap-2">
                                        <button wire:click="assign({{ $vehicle->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Confirm assign</button>
                                        <button wire:click="$set('assigningId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                    </div>
                                </div>
                            @endif

                            @if($fuelId === $vehicle->id)
                                <div class="rounded-sm border border-border bg-bg p-3.5">
                                    <div class="grid grid-cols-3 gap-2">
                                        <input type="date" wire:model="fuelDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="number" wire:model="fuelOdometer" placeholder="Odometer reading" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="number" step="0.01" wire:model="fuelCost" placeholder="Fuel cost" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    </div>
                                    <textarea wire:model="fuelNotes" rows="2" placeholder="Notes" class="mt-2 w-full rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary"></textarea>
                                    @error('fuelOdometer') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    <div class="mt-2 flex gap-2">
                                        <button wire:click="logFuel({{ $vehicle->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save</button>
                                        <button wire:click="$set('fuelId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                    </div>
                                </div>
                            @endif

                            @if($renewalId === $vehicle->id)
                                <div class="rounded-sm border border-border bg-bg p-3.5">
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" wire:model="renewalLabel" placeholder="Label (e.g. Insurance, License)" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="text" wire:model="renewalProvider" placeholder="Provider" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="text" wire:model="renewalReferenceNumber" placeholder="Reference number" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="number" wire:model="renewalMileageInterval" placeholder="Mileage interval (optional)" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="date" wire:model="renewalIssueDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="date" wire:model="renewalExpiryDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    </div>
                                    @error('renewalLabel') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    @error('renewalExpiryDate') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    <div class="mt-2 flex gap-2">
                                        <button wire:click="addRenewal({{ $vehicle->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save</button>
                                        <button wire:click="$set('renewalId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                    </div>
                                </div>
                            @endif

                            @if($customFieldDefs->isNotEmpty())
                                <div class="rounded-sm border border-border bg-bg p-3.5">
                                    <div class="mb-2 text-xs font-semibold text-text">Custom fields</div>
                                    @foreach($customFieldDefs as $def)
                                        <div class="mb-1.5 flex items-center gap-2">
                                            <span class="w-32 text-xs text-text-muted">{{ $def->label }}</span>
                                            @if($def->field_type === 'select')
                                                <select wire:model="customFieldInputs.{{ $def->id }}" class="flex-1 rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                                    <option value="">—</option>
                                                    @foreach($def->options ?? [] as $option)
                                                        <option value="{{ $option }}" @selected($vehicle->customFieldValueFor($def->id) === $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="{{ $def->field_type === 'number' ? 'number' : ($def->field_type === 'date' ? 'date' : 'text') }}" wire:model="customFieldInputs.{{ $def->id }}" value="{{ $vehicle->customFieldValueFor($def->id) }}" class="flex-1 rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                            @endif
                                            <button wire:click="saveCustomField({{ $vehicle->id }}, {{ $def->id }})" class="text-xs font-semibold text-primary">Save</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif

                        @if($vehicle->renewals->isNotEmpty())
                            <div>
                                <div class="mb-1 text-xs font-semibold text-text">Renewals</div>
                                @foreach($vehicle->renewals as $r)
                                    <div class="text-xs text-text-muted">
                                        {{ $r->label }} — expires {{ $r->expiry_date->format('j M Y') }}
                                        @if($r->renewable?->status === 'retired') <span class="text-text-faint">(superseded)</span> @endif
                                        @if($r->mileage_interval)
                                            · service due at {{ $r->due_at_mileage }} mi{{ $r->isMileageDue($vehicle->latestOdometerReading()) ? ' — DUE NOW' : '' }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($vehicle->fuelLogs->isNotEmpty())
                            <div>
                                <div class="mb-1 text-xs font-semibold text-text">Fuel/mileage history</div>
                                @foreach($vehicle->fuelLogs as $log)
                                    <div class="text-xs text-text-muted">{{ $log->log_date->format('j M Y') }} — {{ number_format($log->odometer_reading) }} mi{{ $log->fuel_cost ? ' · ₦'.number_format($log->fuel_cost, 2) : '' }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Assignment history</div>
                            @forelse($vehicle->assignmentHistory as $h)
                                <div class="text-xs text-text-muted">
                                    {{ $h->employee?->fullName() ?? $h->subUnit?->name ?? '—' }} — {{ $h->started_at->format('j M Y') }} to {{ $h->ended_at?->format('j M Y') ?? 'present' }}
                                    @if($h->override_reason) <span class="text-warning">(license override by {{ $h->overriddenBy?->name }}: {{ $h->override_reason }})</span> @endif
                                </div>
                            @empty
                                <div class="text-xs text-text-muted">No assignment history.</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($vehicles->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">{{ $isManager ? 'No vehicles registered yet.' : 'No vehicles are currently assigned to you.' }}</div>
        @endif
    </div>
</div>
