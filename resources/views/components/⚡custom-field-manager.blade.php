<?php

use App\Models\CustomFieldDefinition;
use Livewire\Component;

/** Spec E2/E3: one shared component for defining custom fields, driven by `subjectType` ('asset' | 'vehicle') so E3's Vehicle configuration can reuse it as-is. */
new class extends Component
{
    public string $subjectType = 'asset';

    public string $label = '';

    public string $fieldType = 'text';

    public string $optionsCsv = '';

    public function mount(string $subjectType = 'asset'): void
    {
        $this->subjectType = $subjectType;
    }

    public function create(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:100'],
            'fieldType' => ['required', 'in:'.implode(',', CustomFieldDefinition::FIELD_TYPES)],
        ]);

        CustomFieldDefinition::create([
            'subject_type' => $this->subjectType,
            'label' => $data['label'],
            'field_type' => $data['fieldType'],
            'options' => $data['fieldType'] === 'select'
                ? array_values(array_filter(array_map('trim', explode(',', $this->optionsCsv))))
                : null,
            'sort_order' => CustomFieldDefinition::where('subject_type', $this->subjectType)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        $this->reset('label', 'fieldType', 'optionsCsv');
        session()->flash('status', 'Custom field added.');
    }

    public function toggleActive(int $id): void
    {
        $def = CustomFieldDefinition::findOrFail($id);
        $def->update(['is_active' => ! $def->is_active]);
    }

    public function with(): array
    {
        return [
            'definitions' => CustomFieldDefinition::where('subject_type', $this->subjectType)->orderBy('sort_order')->get(),
            'fieldTypes' => CustomFieldDefinition::FIELD_TYPES,
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New field</h2>
        <form wire:submit="create" class="flex flex-wrap items-end gap-3">
            <div style="flex:1;min-width:200px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Label</label>
                <input type="text" wire:model="label" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('label') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Type</label>
                <select wire:model.live="fieldType" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @foreach($fieldTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach
                </select>
            </div>
            @if($fieldType === 'select')
                <div style="flex:1;min-width:200px;">
                    <label class="mb-1.5 block text-xs font-semibold text-text">Options (comma-separated)</label>
                    <input type="text" wire:model="optionsCsv" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
            @endif
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add field</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Label</th>
                    <th class="px-4 py-2.5">Type</th>
                    <th class="px-4 py-2.5">Options</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($definitions as $def)
                    <tr class="border-b border-border last:border-0 {{ $def->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2.5 text-text">{{ $def->label }}</td>
                        <td class="px-4 py-2.5 text-text">{{ ucfirst($def->field_type) }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $def->options ? implode(', ', $def->options) : '—' }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $def->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-2.5">
                            <button wire:click="toggleActive({{ $def->id }})" class="text-xs font-semibold text-primary">{{ $def->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </td>
                    </tr>
                @endforeach
                @if($definitions->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="5">No custom fields configured yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
