<?php

use App\Models\AuditLog;
use App\Models\CompanyRegistrationDocument;
use App\Models\DocumentCategory;
use App\Services\CompanyDocumentService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $creatingDocument = false;

    public string $title = '';

    public ?int $documentCategoryId = null;

    public string $description = '';

    public bool $isRenewable = false;

    public ?int $addingVersionTo = null;

    public string $issueDate = '';

    public string $expiryDate = '';

    public string $notes = '';

    public $file = null;

    public ?int $expandedId = null;

    /** Spec E5: renewable is decided once, at upload — "there is no separate 'configure later' step." */
    public function createDocument(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'documentCategoryId' => ['required', 'exists:document_categories,id'],
        ]);

        CompanyRegistrationDocument::create([
            'title' => $data['title'],
            'document_category_id' => $data['documentCategoryId'],
            'description' => $this->description ?: null,
            'is_renewable' => $this->isRenewable,
        ]);

        $this->reset('creatingDocument', 'title', 'documentCategoryId', 'description', 'isRenewable');
        session()->flash('status', 'Document created.');
    }

    public function addVersion(int $id, CompanyDocumentService $documents): void
    {
        $document = CompanyRegistrationDocument::findOrFail($id);

        $rules = [
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
        if ($document->is_renewable) {
            $rules['issueDate'] = ['required', 'date'];
            $rules['expiryDate'] = ['required', 'date', 'after:issueDate'];
        }

        $data = $this->validate($rules);

        try {
            $documents->addVersion(
                $document,
                $document->is_renewable ? \Illuminate\Support\Carbon::parse($data['issueDate']) : null,
                $document->is_renewable ? \Illuminate\Support\Carbon::parse($data['expiryDate']) : null,
                $this->file,
                $data['notes'] ?: null,
                auth()->user(),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('expiryDate', collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('addingVersionTo', 'issueDate', 'expiryDate', 'notes', 'file');
        session()->flash('status', 'Document version saved.');
    }

    public function with(): array
    {
        return [
            'documents' => CompanyRegistrationDocument::with(['category', 'versions.renewable'])->orderBy('title')->get(),
            'categories' => DocumentCategory::orderBy('name')->get(),
            'auditLogs' => $this->expandedId
                ? AuditLog::where('auditable_type', CompanyRegistrationDocument::class)->where('auditable_id', $this->expandedId)->latest()->get()
                : collect(),
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
                    <select wire:model="documentCategoryId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('documentCategoryId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <label class="flex items-center gap-1.5 pb-2.5 text-xs text-text">
                    <input type="checkbox" wire:model="isRenewable" class="h-3.5 w-3.5 accent-primary">
                    Renewable (requires issue/expiry dates on every version)
                </label>
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
                        <div class="text-xs text-text-muted">{{ $doc->category->name }} · {{ $doc->is_renewable ? 'Renewable' : 'Non-renewable' }} · current version: {{ $doc->currentVersion() ? $doc->currentVersion()->created_at->format('j M Y') : 'none yet' }}</div>
                    </button>
                    @if($doc->is_renewable && $doc->currentVersion())
                        @php($renewable = $doc->currentVersion()->renewable)
                        <span class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $renewable?->status === 'expired' ? 'bg-danger/10 text-danger' : 'bg-accent-light text-accent' }}">{{ $renewable?->status === 'expired' ? 'Expired' : 'Active' }}</span>
                    @endif
                </div>

                @if($expandedId === $doc->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        <button wire:click="$set('addingVersionTo', {{ $doc->id }})" class="self-start text-xs font-semibold text-primary">{{ $doc->currentVersion() ? 'Renew (new version)' : 'Upload initial version' }}</button>

                        @if($addingVersionTo === $doc->id)
                            <div class="rounded-sm border border-border bg-bg p-3.5">
                                @if($doc->is_renewable)
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="date" wire:model="issueDate" placeholder="Issue date" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                        <input type="date" wire:model="expiryDate" placeholder="Expiry date" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    </div>
                                @endif
                                <textarea wire:model="notes" rows="2" placeholder="Notes" class="mt-2 w-full rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary"></textarea>
                                <input type="file" wire:model="file" class="mt-2 w-full text-xs">
                                @error('issueDate') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                @error('expiryDate') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                @error('file') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="addVersion({{ $doc->id }})" wire:loading.attr="disabled" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save</button>
                                    <button wire:click="$set('addingVersionTo', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                </div>
                            </div>
                        @endif

                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Version history</div>
                            @forelse($doc->versions as $v)
                                <div class="text-xs text-text-muted">
                                    {{ $v->created_at->format('j M Y') }}
                                    @if($v->issue_date) — issued {{ $v->issue_date->format('j M Y') }}, expires {{ $v->expiry_date->format('j M Y') }} @endif
                                    @if($v->notes) — {{ $v->notes }} @endif
                                    — <a href="{{ route('company-documents.file', $v) }}" class="font-semibold text-primary" target="_blank">Download</a>
                                </div>
                            @empty
                                <div class="text-xs text-text-muted">No versions uploaded yet.</div>
                            @endforelse
                        </div>

                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Access history (audit log)</div>
                            @forelse($auditLogs as $log)
                                <div class="text-xs text-text-muted">{{ $log->created_at->format('j M Y, g:ia') }} — {{ $log->actor_label }} {{ $log->action }}</div>
                            @empty
                                <div class="text-xs text-text-muted">No recorded access yet.</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($documents->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No company registration documents yet.</div>
        @endif
    </div>
</div>
