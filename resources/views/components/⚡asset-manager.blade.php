<?php

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CustomFieldValue;
use App\Models\Employee;
use App\Services\AssetService;
use App\Services\PermissionService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Spec E2: full register for Admin/HR Admin/HR Officer; an ordinary ESS user sees only assets currently assigned to them, plus their own assignment history. */
new class extends Component
{
    public bool $creating = false;

    public string $tag = '';

    public string $name = '';

    public ?int $assetCategoryId = null;

    public string $serialNumber = '';

    public string $purchaseDate = '';

    public string $purchaseCost = '';

    public ?int $expandedId = null;

    public ?int $assigningId = null;

    public ?int $assignEmployeeId = null;

    public ?int $maintenanceId = null;

    public string $maintenanceDate = '';

    public string $maintenanceDescription = '';

    public string $maintenanceCost = '';

    public string $maintenanceVendor = '';

    public bool $setInRepair = false;

    public ?int $warrantyId = null;

    public string $warrantyProvider = '';

    public string $warrantyContractNumber = '';

    public string $warrantyIssueDate = '';

    public string $warrantyExpiryDate = '';

    public array $customFieldInputs = [];

    public function create(): void
    {
        $data = $this->validate([
            'tag' => ['required', 'string', 'max:50', 'unique:assets,tag'],
            'name' => ['required', 'string', 'max:150'],
            'assetCategoryId' => ['nullable', 'exists:asset_categories,id'],
            'serialNumber' => ['nullable', 'string', 'max:100'],
            'purchaseDate' => ['nullable', 'date'],
            'purchaseCost' => ['nullable', 'numeric', 'min:0'],
        ]);

        Asset::create([
            'tag' => $data['tag'],
            'name' => $data['name'],
            'asset_category_id' => $data['assetCategoryId'],
            'serial_number' => $data['serialNumber'] ?: null,
            'purchase_date' => $data['purchaseDate'] ?: null,
            'purchase_cost' => $data['purchaseCost'] ?: null,
            'status' => 'available',
        ]);

        $this->reset('creating', 'tag', 'name', 'assetCategoryId', 'serialNumber', 'purchaseDate', 'purchaseCost');
        session()->flash('status', 'Asset added.');
    }

    public function assign(int $id, AssetService $assets): void
    {
        $data = $this->validate(['assignEmployeeId' => ['required', 'exists:employees,id']]);
        $assets->assign(Asset::findOrFail($id), Employee::findOrFail($data['assignEmployeeId']));
        $this->reset('assigningId', 'assignEmployeeId');
        session()->flash('status', 'Asset assigned.');
    }

    public function unassign(int $id, AssetService $assets): void
    {
        $assets->unassign(Asset::findOrFail($id));
        session()->flash('status', 'Asset unassigned.');
    }

    public function retire(int $id, AssetService $assets): void
    {
        $assets->retire(Asset::findOrFail($id));
        session()->flash('status', 'Asset retired.');
    }

    public function logMaintenance(int $id, AssetService $assets): void
    {
        $data = $this->validate([
            'maintenanceDate' => ['required', 'date'],
            'maintenanceDescription' => ['required', 'string', 'max:1000'],
            'maintenanceCost' => ['nullable', 'numeric', 'min:0'],
            'maintenanceVendor' => ['nullable', 'string', 'max:150'],
        ]);

        $assets->logMaintenance(
            Asset::findOrFail($id),
            \Illuminate\Support\Carbon::parse($data['maintenanceDate']),
            $data['maintenanceDescription'],
            $data['maintenanceCost'] ?: null,
            $data['maintenanceVendor'] ?: null,
            $this->setInRepair,
        );

        $this->reset('maintenanceId', 'maintenanceDate', 'maintenanceDescription', 'maintenanceCost', 'maintenanceVendor', 'setInRepair');
        session()->flash('status', 'Maintenance logged.');
    }

    public function addWarranty(int $id, AssetService $assets): void
    {
        $data = $this->validate([
            'warrantyProvider' => ['required', 'string', 'max:150'],
            'warrantyContractNumber' => ['nullable', 'string', 'max:100'],
            'warrantyIssueDate' => ['required', 'date'],
            'warrantyExpiryDate' => ['required', 'date', 'after:warrantyIssueDate'],
        ]);

        try {
            $assets->addWarranty(
                Asset::findOrFail($id),
                $data['warrantyProvider'],
                $data['warrantyContractNumber'] ?: null,
                \Illuminate\Support\Carbon::parse($data['warrantyIssueDate']),
                \Illuminate\Support\Carbon::parse($data['warrantyExpiryDate']),
                null,
            );
        } catch (ValidationException $e) {
            $this->addError('warrantyExpiryDate', $e->errors()['expiryDate'][0]);

            return;
        }

        $this->reset('warrantyId', 'warrantyProvider', 'warrantyContractNumber', 'warrantyIssueDate', 'warrantyExpiryDate');
        session()->flash('status', 'Warranty added.');
    }

    public function saveCustomField(int $assetId, int $definitionId): void
    {
        // If the field was never touched (still bound to its HTML-hydrated
        // initial value, not a real Livewire-tracked one), fall back to
        // whatever is already stored rather than overwriting it with null.
        $value = array_key_exists($definitionId, $this->customFieldInputs)
            ? $this->customFieldInputs[$definitionId]
            : CustomFieldValue::where(['definition_id' => $definitionId, 'valuable_type' => Asset::class, 'valuable_id' => $assetId])->value('value');

        CustomFieldValue::updateOrCreate(
            ['definition_id' => $definitionId, 'valuable_type' => Asset::class, 'valuable_id' => $assetId],
            ['value' => $value]
        );

        session()->flash('status', 'Custom field saved.');
    }

    public function with(PermissionService $permissions): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'assets');
        $isManager = $scope === 'all';

        $assets = Asset::with(['category', 'currentEmployee', 'assignmentHistory.employee', 'maintenanceLogs', 'warranties', 'customFieldValues.definition'])
            ->when(! $isManager, fn ($q) => $q->where('current_employee_id', $me?->id))
            ->latest()
            ->get();

        return [
            'assets' => $assets,
            'isManager' => $isManager,
            'categories' => AssetCategory::orderBy('name')->get(),
            'employees' => Employee::orderBy('last_name')->get(),
            'customFieldDefs' => \App\Models\CustomFieldDefinition::where('subject_type', 'asset')->where('is_active', true)->orderBy('sort_order')->get(),
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
                <button wire:click="$set('creating', true)" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add asset</button>
            @else
                <h2 class="mb-3.5 font-display text-base font-bold text-text">New asset</h2>
                <form wire:submit="create" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Tag</label>
                        <input type="text" wire:model="tag" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @error('tag') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Name</label>
                        <input type="text" wire:model="name" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Category</label>
                        <select wire:model="assetCategoryId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                            <option value="">— none —</option>
                            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Serial number</label>
                        <input type="text" wire:model="serialNumber" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Purchase date</label>
                        <input type="date" wire:model="purchaseDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Purchase cost</label>
                        <input type="number" step="0.01" wire:model="purchaseCost" class="w-32 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                    <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
                    <button type="button" wire:click="$set('creating', false)" class="text-sm font-semibold text-text-muted">Cancel</button>
                </form>
            @endif
        </section>
    @endif

    <div class="flex flex-col gap-3">
        @foreach($assets as $asset)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button wire:click="$set('expandedId', {{ $expandedId === $asset->id ? 'null' : $asset->id }})" class="text-left">
                        <div class="font-display text-sm font-bold text-text">{{ $asset->name }} <span class="font-mono text-xs text-text-muted">{{ $asset->tag }}</span></div>
                        <div class="text-xs text-text-muted">{{ $asset->category?->name ?? 'Uncategorized' }} · {{ $asset->currentEmployee?->fullName() ?? 'Unassigned' }}</div>
                    </button>
                    <span class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $asset->status === 'assigned' ? 'bg-info-light text-info' : ($asset->status === 'in_repair' ? 'bg-warning-light text-warning' : ($asset->status === 'retired' ? 'bg-text-faint/15 text-text-muted' : 'bg-accent-light text-accent')) }}">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</span>
                </div>

                @if($expandedId === $asset->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        @if($isManager)
                            <div class="flex flex-wrap gap-2">
                                @if($asset->status !== 'retired')
                                    @if($asset->current_employee_id)
                                        <button wire:click="unassign({{ $asset->id }})" class="text-xs font-semibold text-primary">Unassign</button>
                                    @else
                                        <button wire:click="$set('assigningId', {{ $asset->id }})" class="text-xs font-semibold text-primary">Assign</button>
                                    @endif
                                    <button wire:click="$set('maintenanceId', {{ $asset->id }})" class="text-xs font-semibold text-primary">Log maintenance</button>
                                    <button wire:click="$set('warrantyId', {{ $asset->id }})" class="text-xs font-semibold text-primary">Add warranty</button>
                                    <button wire:click="retire({{ $asset->id }})" wire:confirm="Retire this asset?" class="text-xs font-semibold text-danger">Retire</button>
                                @endif
                            </div>

                            @if($assigningId === $asset->id)
                                <div class="rounded-sm border border-border bg-bg p-3.5">
                                    <select wire:model="assignEmployeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                        <option value="">— select employee —</option>
                                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                                    </select>
                                    @error('assignEmployeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    <div class="mt-2 flex gap-2">
                                        <button wire:click="assign({{ $asset->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Confirm assign</button>
                                        <button wire:click="$set('assigningId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                    </div>
                                </div>
                            @endif

                            @if($maintenanceId === $asset->id)
                                <div class="rounded-sm border border-border bg-bg p-3.5">
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="date" wire:model="maintenanceDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="text" wire:model="maintenanceVendor" placeholder="Vendor" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    </div>
                                    <textarea wire:model="maintenanceDescription" rows="2" placeholder="Description" class="mt-2 w-full rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary"></textarea>
                                    <input type="number" step="0.01" wire:model="maintenanceCost" placeholder="Cost" class="mt-2 w-32 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    <label class="mt-2 flex items-center gap-1.5 text-xs text-text">
                                        <input type="checkbox" wire:model="setInRepair" class="h-3.5 w-3.5 accent-primary">
                                        Mark asset "In Repair"
                                    </label>
                                    @error('maintenanceDescription') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    <div class="mt-2 flex gap-2">
                                        <button wire:click="logMaintenance({{ $asset->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save</button>
                                        <button wire:click="$set('maintenanceId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                    </div>
                                </div>
                            @endif

                            @if($warrantyId === $asset->id)
                                <div class="rounded-sm border border-border bg-bg p-3.5">
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" wire:model="warrantyProvider" placeholder="Provider" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="text" wire:model="warrantyContractNumber" placeholder="Contract number" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="date" wire:model="warrantyIssueDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="date" wire:model="warrantyExpiryDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    </div>
                                    @error('warrantyProvider') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    @error('warrantyExpiryDate') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                    <div class="mt-2 flex gap-2">
                                        <button wire:click="addWarranty({{ $asset->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save</button>
                                        <button wire:click="$set('warrantyId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
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
                                                        <option value="{{ $option }}" @selected($asset->customFieldValueFor($def->id) === $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="{{ $def->field_type === 'number' ? 'number' : ($def->field_type === 'date' ? 'date' : 'text') }}" wire:model="customFieldInputs.{{ $def->id }}" value="{{ $asset->customFieldValueFor($def->id) }}" class="flex-1 rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                            @endif
                                            <button wire:click="saveCustomField({{ $asset->id }}, {{ $def->id }})" class="text-xs font-semibold text-primary">Save</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif

                        @if($asset->warranties->isNotEmpty())
                            <div>
                                <div class="mb-1 text-xs font-semibold text-text">Warranties</div>
                                @foreach($asset->warranties as $w)
                                    <div class="text-xs text-text-muted">{{ $w->provider }} — expires {{ $w->expiry_date->format('j M Y') }}</div>
                                @endforeach
                            </div>
                        @endif

                        @if($asset->maintenanceLogs->isNotEmpty())
                            <div>
                                <div class="mb-1 text-xs font-semibold text-text">Maintenance history</div>
                                @foreach($asset->maintenanceLogs as $log)
                                    <div class="text-xs text-text-muted">{{ $log->log_date->format('j M Y') }} — {{ $log->description }}{{ $log->vendor ? " ({$log->vendor})" : '' }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Assignment history</div>
                            @forelse($asset->assignmentHistory as $h)
                                <div class="text-xs text-text-muted">{{ $h->employee?->fullName() ?? '—' }} — {{ $h->started_at->format('j M Y') }} to {{ $h->ended_at?->format('j M Y') ?? 'present' }}</div>
                            @empty
                                <div class="text-xs text-text-muted">No assignment history.</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($assets->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">{{ $isManager ? 'No assets registered yet.' : 'No assets are currently assigned to you.' }}</div>
        @endif
    </div>
</div>
