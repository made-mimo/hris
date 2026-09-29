<?php

use App\Models\Employee;
use App\Models\EmployeeCustomFieldDefinition;
use App\Models\EmployeeCustomFieldValue;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Employee $employee;

    public $file = null;

    public string $tab = 'personal';

    public string $description = '';

    public array $customValues = [];

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        foreach ($employee->customFieldValues as $v) {
            $this->customValues[$v->definition_id] = $v->value;
        }
    }

    /**
     * Not named upload() — that name collides with Livewire's own built-in
     * $wire.upload() JS helper (used for file-upload progress), so a
     * wire:submit/wire:click bound to an action of that exact name silently
     * calls Livewire's helper instead of this method (found via the SI PIM
     * session, which hit the identical bug there).
     */
    public function uploadDocument(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'tab' => ['required', 'in:'.implode(',', EmployeeCustomFieldDefinition::TABS)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $this->employee->addMedia($this->file->getRealPath())
            ->usingName($this->file->getClientOriginalName())
            ->withCustomProperties(['tab' => $this->tab, 'description' => $this->description])
            ->toMediaCollection('documents');

        $this->reset('file', 'description');
        session()->flash('status', 'Document uploaded.');
    }

    public function deleteDocument(int $mediaId): void
    {
        $this->employee->media()->where('id', $mediaId)->firstOrFail()->delete();
        session()->flash('status', 'Document removed.');
    }

    public function saveCustomFields(): void
    {
        foreach ($this->customValues as $definitionId => $value) {
            EmployeeCustomFieldValue::updateOrCreate(
                ['employee_id' => $this->employee->id, 'definition_id' => $definitionId],
                ['value' => $value]
            );
        }

        session()->flash('status', 'Custom fields updated.');
    }

    public function with(): array
    {
        return [
            'documents' => $this->employee->documentsUrl(),
            'definitions' => EmployeeCustomFieldDefinition::where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Attachments</h2>
        <form wire:submit="uploadDocument" class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">File</label>
                <input type="file" wire:model="file" class="text-sm text-text">
                @error('file') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Tab</label>
                <select wire:model="tab" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @foreach(\App\Models\EmployeeCustomFieldDefinition::TABS as $t)
                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[200px] flex-1">
                <label class="mb-1.5 block text-xs font-semibold text-text">Description <span class="text-text-muted">(optional)</span></label>
                <input type="text" wire:model="description" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Upload</button>
        </form>

        <div class="divide-y divide-border">
            @foreach($documents as $doc)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <div class="flex items-center gap-2.5">
                        <a href="{{ $doc['url'] }}" target="_blank" class="font-medium text-primary hover:underline">{{ $doc['name'] }}</a>
                        <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ ucfirst($doc['tab']) }}</span>
                        @if($doc['description']) <span class="text-text-muted">{{ $doc['description'] }}</span> @endif
                    </div>
                    <button wire:click="deleteDocument({{ $doc['id'] }})" wire:confirm="Delete this document?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if(empty($documents))
                <div class="py-4 text-center text-sm text-text-muted">No documents uploaded yet.</div>
            @endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Custom fields</h2>
        @if($definitions->isEmpty())
            <div class="text-sm text-text-muted">No custom fields configured. An Admin can define up to 10 under Organization &amp; Master Data.</div>
        @else
            <form wire:submit="saveCustomFields" class="flex flex-col gap-3.5">
                @foreach($definitions as $def)
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">{{ $def->label }} <span class="text-text-muted">({{ ucfirst($def->tab) }})</span></label>
                        @if($def->field_type === 'select')
                            <select wire:model="customValues.{{ $def->id }}" class="w-full max-w-xs rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                <option value="">— none —</option>
                                @foreach(($def->options ?? []) as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" wire:model="customValues.{{ $def->id }}" class="w-full max-w-xs rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @endif
                    </div>
                @endforeach
                <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Save custom fields</button>
            </form>
        @endif
    </section>
</div>
