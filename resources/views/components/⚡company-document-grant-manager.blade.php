<?php

use App\Models\CompanyDocumentFileAccessGrant;
use App\Models\CompanyRegistrationDocument;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\Role;
use App\Services\CompanyDocumentService;
use Livewire\Component;

/** Spec E5: "restricted by default...a specific document OR category can be granted broader visibility." */
new class extends Component
{
    public string $scopeType = 'category';

    public ?int $documentCategoryId = null;

    public ?int $documentId = null;

    public string $grantType = 'role';

    public ?int $roleId = null;

    public ?int $employeeId = null;

    public function grant(CompanyDocumentService $documents): void
    {
        $data = $this->validate([
            'documentCategoryId' => ['required_if:scopeType,category', 'nullable', 'exists:document_categories,id'],
            'documentId' => ['required_if:scopeType,document', 'nullable', 'exists:company_registration_documents,id'],
            'roleId' => ['required_if:grantType,role', 'nullable', 'exists:roles,id'],
            'employeeId' => ['required_if:grantType,employee', 'nullable', 'exists:employees,id'],
        ]);

        $documents->grantAccess(
            $this->scopeType === 'category' ? $data['documentCategoryId'] : null,
            $this->scopeType === 'document' ? $data['documentId'] : null,
            $this->grantType === 'role' ? $data['roleId'] : null,
            $this->grantType === 'employee' ? $data['employeeId'] : null,
        );

        $this->reset('documentCategoryId', 'documentId', 'roleId', 'employeeId');
        session()->flash('status', 'Access granted.');
    }

    public function revoke(int $id): void
    {
        CompanyDocumentFileAccessGrant::findOrFail($id)->delete();
        session()->flash('status', 'Access grant revoked.');
    }

    public function with(): array
    {
        return [
            'categories' => DocumentCategory::orderBy('name')->get(),
            'documents' => CompanyRegistrationDocument::orderBy('title')->get(),
            'roles' => Role::orderBy('name')->get(),
            'employees' => Employee::orderBy('last_name')->get(),
            'grants' => CompanyDocumentFileAccessGrant::with(['category', 'document', 'role', 'employee'])->latest()->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Grant access</h2>
        <form wire:submit="grant" class="flex flex-wrap items-end gap-3">
            <div class="flex gap-3 pb-2.5 text-xs">
                <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="scopeType" value="category" class="accent-primary"> Whole category</label>
                <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="scopeType" value="document" class="accent-primary"> Specific document</label>
            </div>
            @if($scopeType === 'category')
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Category</label>
                    <select wire:model.live="documentCategoryId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('documentCategoryId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            @else
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Document</label>
                    <select wire:model.live="documentId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($documents as $d)<option value="{{ $d->id }}">{{ $d->title }}</option>@endforeach
                    </select>
                    @error('documentId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            @endif
            <div class="flex gap-3 pb-2.5 text-xs">
                <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="grantType" value="role" class="accent-primary"> By role</label>
                <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="grantType" value="employee" class="accent-primary"> By employee</label>
            </div>
            @if($grantType === 'role')
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Role</label>
                    <select wire:model.live="roleId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                    </select>
                    @error('roleId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            @else
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                    <select wire:model.live="employeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                    </select>
                    @error('employeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            @endif
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Grant</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Scope</th>
                    <th class="px-4 py-2.5">Granted to</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($grants as $grant)
                    <tr class="border-b border-border last:border-0">
                        <td class="px-4 py-2.5 text-text">{{ $grant->category ? 'Category: '.$grant->category->name : 'Document: '.$grant->document?->title }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $grant->role ? 'Role: '.$grant->role->name : 'Employee: '.$grant->employee?->fullName() }}</td>
                        <td class="px-4 py-2.5">
                            <button wire:click="revoke({{ $grant->id }})" wire:confirm="Revoke this access grant?" class="text-xs font-semibold text-danger">Revoke</button>
                        </td>
                    </tr>
                @endforeach
                @if($grants->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="3">No access grants yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
