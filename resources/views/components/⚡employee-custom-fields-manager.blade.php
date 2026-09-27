<?php

use App\Models\EmployeeCustomFieldDefinition;
use Livewire\Component;

/** Spec B2: "up to 10 organization-configurable custom fields (label, type — free text or select — and which tab they render on)." The 10-field cap is enforced here, not in the schema. */
new class extends Component
{
    public const MAX_FIELDS = 10;

    public string $label = '';

    public string $fieldType = 'text';

    public string $tab = 'personal';

    public string $optionsCsv = '';

    public ?int $editingId = null;

    public function create(): void
    {
        if (EmployeeCustomFieldDefinition::count() >= self::MAX_FIELDS) {
            session()->flash('error', 'Maximum of '.self::MAX_FIELDS.' custom fields reached.');

            return;
        }

        $this->validate([
            'label' => ['required', 'string', 'max:100'],
            'fieldType' => ['required', 'in:text,select'],
            'tab' => ['required', 'in:'.implode(',', EmployeeCustomFieldDefinition::TABS)],
        ]);

        EmployeeCustomFieldDefinition::create([
            'label' => $this->label,
            'field_type' => $this->fieldType,
            'tab' => $this->tab,
            'options' => $this->fieldType === 'select' ? array_values(array_filter(array_map('trim', explode(',', $this->optionsCsv)))) : null,
            'sort_order' => EmployeeCustomFieldDefinition::max('sort_order') + 1,
        ]);

        $this->reset('label', 'optionsCsv');
        $this->fieldType = 'text';
        session()->flash('status', 'Custom field added.');
    }

    public function toggleActive(int $id): void
    {
        $def = EmployeeCustomFieldDefinition::findOrFail($id);
        $def->update(['is_active' => ! $def->is_active]);
    }

    public function delete(int $id): void
    {
        EmployeeCustomFieldDefinition::findOrFail($id)->delete();
        session()->flash('status', 'Custom field removed.');
    }

    public function with(): array
    {
        return [
            'definitions' => EmployeeCustomFieldDefinition::orderBy('sort_order')->get(),
            'atLimit' => EmployeeCustomFieldDefinition::count() >= self::MAX_FIELDS,
        ];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-danger/10 px-3.5 py-2.5 text-xs font-semibold text-danger">{{ session('error') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3 text-xs text-text-muted">{{ $definitions->count() }} of {{ self::MAX_FIELDS }} fields used. Values are entered per-employee on the Attachments &amp; Custom Fields tab.</div>

        @if(! $atLimit)
            <form wire:submit="create" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 md:items-end">
                <input type="text" wire:model="label" placeholder="Label" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <select wire:model.live="fieldType" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @foreach(\App\Models\EmployeeCustomFieldDefinition::TYPES as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                </select>
                <select wire:model="tab" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @foreach(\App\Models\EmployeeCustomFieldDefinition::TABS as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                </select>
                <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
                @if($fieldType === 'select')
                    <input type="text" wire:model="optionsCsv" placeholder="Options, comma-separated" class="col-span-2 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary md:col-span-3">
                @endif
            </form>
            @error('label') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @endif

        <div class="divide-y divide-border">
            @foreach($definitions as $def)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="font-medium text-text">{{ $def->label }}</span>
                        <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ ucfirst($def->field_type) }}</span>
                        <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ ucfirst($def->tab) }}</span>
                        @if(! $def->is_active) <span class="rounded-pill bg-danger/10 px-2 py-0.5 text-[10px] font-semibold text-danger">Inactive</span> @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="toggleActive({{ $def->id }})" class="text-xs font-semibold text-primary">{{ $def->is_active ? 'Deactivate' : 'Activate' }}</button>
                        <button wire:click="delete({{ $def->id }})" wire:confirm="Delete this custom field and all its stored values?" class="text-xs font-semibold text-danger">Delete</button>
                    </div>
                </div>
            @endforeach
            @if($definitions->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No custom fields defined yet.</div>
            @endif
        </div>
    </section>
</div>
