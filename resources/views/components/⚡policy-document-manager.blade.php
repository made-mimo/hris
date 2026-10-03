<?php

use App\Models\PolicyCategory;
use App\Models\PolicyDocument;
use App\Services\PolicyService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $creatingDocument = false;

    public string $title = '';

    public ?int $policyCategoryId = null;

    public ?int $addingVersionTo = null;

    public string $versionLabel = '';

    public string $changeNotes = '';

    public string $effectiveDate = '';

    public $file = null;

    public ?int $expandedId = null;

    public array $compliance = [];

    public function createDocument(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'policyCategoryId' => ['required', 'exists:policy_categories,id'],
        ]);

        PolicyDocument::create(['title' => $data['title'], 'policy_category_id' => $data['policyCategoryId'], 'is_active' => true]);

        $this->reset('creatingDocument', 'title', 'policyCategoryId');
        session()->flash('status', 'Document created.');
    }

    public function toggleActive(int $id): void
    {
        $doc = PolicyDocument::findOrFail($id);
        $doc->update(['is_active' => ! $doc->is_active]);
        session()->flash('status', 'Document updated.');
    }

    public function addVersion(int $id, PolicyService $policy): void
    {
        $data = $this->validate([
            'versionLabel' => ['required', 'string', 'max:50'],
            'changeNotes' => ['nullable', 'string', 'max:1000'],
            'effectiveDate' => ['required', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $policy->addVersion(
            PolicyDocument::findOrFail($id),
            $data['versionLabel'],
            $data['changeNotes'] ?: null,
            \Illuminate\Support\Carbon::parse($data['effectiveDate']),
            $this->file,
        );

        $this->reset('addingVersionTo', 'versionLabel', 'changeNotes', 'effectiveDate', 'file');
        session()->flash('status', 'New version published.');
    }

    public function viewCompliance(int $documentId, PolicyService $policy): void
    {
        $doc = PolicyDocument::findOrFail($documentId);
        $version = $doc->currentVersion();

        if ($version) {
            $this->compliance[$documentId] = $policy->complianceReport($version);
        }
    }

    public function with(): array
    {
        return [
            'documents' => PolicyDocument::with(['category', 'versions'])->orderBy('title')->get(),
            'categories' => PolicyCategory::orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        @if(! $creatingDocument)
            <button wire:click="$set('creatingDocument', true)" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">New document</button>
        @else
            <form wire:submit="createDocument" class="flex flex-wrap items-end gap-3">
                <div style="flex:1;min-width:200px;">
                    <label class="mb-1.5 block text-xs font-semibold text-text">Title</label>
                    <input type="text" wire:model="title" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('title') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Category</label>
                    <select wire:model="policyCategoryId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->is_restricted ? ' (restricted)' : '' }}</option>@endforeach
                    </select>
                    @error('policyCategoryId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Create</button>
                <button type="button" wire:click="$set('creatingDocument', false)" class="text-sm font-semibold text-text-muted">Cancel</button>
            </form>
        @endif
    </section>

    <div class="flex flex-col gap-3">
        @foreach($documents as $doc)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button wire:click="$set('expandedId', {{ $expandedId === $doc->id ? 'null' : $doc->id }})" class="text-left">
                        <div class="font-display text-sm font-bold text-text">{{ $doc->title }}</div>
                        <div class="text-xs text-text-muted">{{ $doc->category->name }}{{ $doc->category->is_restricted ? ' · restricted' : '' }} · current version: {{ $doc->currentVersion()?->version_label ?? 'none yet' }}</div>
                    </button>
                    <button wire:click="toggleActive({{ $doc->id }})" class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $doc->is_active ? 'bg-accent-light text-accent' : 'bg-text-faint/15 text-text-muted' }}">{{ $doc->is_active ? 'Active' : 'Inactive' }}</button>
                </div>

                @if($expandedId === $doc->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        <div class="flex flex-wrap gap-2">
                            <button wire:click="$set('addingVersionTo', {{ $doc->id }})" class="text-xs font-semibold text-primary">Publish new version</button>
                            <button wire:click="viewCompliance({{ $doc->id }})" class="text-xs font-semibold text-primary">View compliance</button>
                        </div>

                        @if($addingVersionTo === $doc->id)
                            <div class="rounded-sm border border-border bg-bg p-3.5">
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" wire:model="versionLabel" placeholder="Version label (e.g. v2.0)" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    <input type="date" wire:model="effectiveDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                </div>
                                <textarea wire:model="changeNotes" rows="2" placeholder="Change notes" class="mt-2 w-full rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary"></textarea>
                                <div class="mt-2"><x-file-input model="file" :selected="$file" /></div>
                                @error('versionLabel') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                @error('effectiveDate') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                @error('file') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="addVersion({{ $doc->id }})" wire:loading.attr="disabled" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Publish</button>
                                    <button wire:click="$set('addingVersionTo', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                </div>
                            </div>
                        @endif

                        @if(isset($compliance[$doc->id]))
                            <div class="rounded-sm border border-border bg-bg p-3.5 text-xs">
                                <div class="font-semibold text-text">Compliance for {{ $doc->currentVersion()->version_label }}</div>
                                <div class="mt-1 text-text-muted">{{ $compliance[$doc->id]['acknowledged'] }} of {{ $compliance[$doc->id]['total'] }} active employees have acknowledged ({{ $compliance[$doc->id]['outstanding'] }} outstanding).</div>
                                @if($compliance[$doc->id]['outstandingEmployees']->isNotEmpty())
                                    <div class="mt-1.5 text-text-muted">Outstanding: {{ $compliance[$doc->id]['outstandingEmployees']->map(fn($e) => $e->fullName())->implode(', ') }}</div>
                                @endif
                            </div>
                        @endif

                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Version history</div>
                            @foreach($doc->versions as $v)
                                <div class="text-xs text-text-muted">{{ $v->version_label }} — effective {{ $v->effective_date->format('j M Y') }}{{ $v->change_notes ? ' — '.$v->change_notes : '' }} — <a href="{{ route('policies.file', $v) }}" class="font-semibold text-primary" target="_blank">Download</a></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($documents->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No policy documents yet.</div>
        @endif
    </div>
</div>
