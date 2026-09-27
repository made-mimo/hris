<?php

use App\Models\Employee;
use App\Models\EmployeeImmigrationRecord;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public string $documentType = '';

    public string $documentNumber = '';

    public string $issueDate = '';

    public string $expiryDate = '';

    public string $status = 'valid';

    public string $reviewDate = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function add(): void
    {
        $this->validate([
            'documentType' => ['required', 'string', 'max:100'],
            'documentNumber' => ['required', 'string', 'max:255'],
            'issueDate' => ['nullable', 'date'],
            'expiryDate' => ['nullable', 'date'],
            'reviewDate' => ['nullable', 'date'],
        ]);

        $this->employee->immigrationRecords()->create([
            'document_type' => $this->documentType,
            'document_number' => $this->documentNumber,
            'issue_date' => $this->issueDate ?: null,
            'expiry_date' => $this->expiryDate ?: null,
            'status' => $this->status,
            'review_date' => $this->reviewDate ?: null,
        ]);

        $this->reset('documentType', 'documentNumber', 'issueDate', 'expiryDate', 'reviewDate');
        $this->status = 'valid';
        session()->flash('status', 'Immigration record added.');
    }

    public function delete(int $id): void
    {
        EmployeeImmigrationRecord::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
        session()->flash('status', 'Immigration record removed.');
    }

    public function with(): array
    {
        return ['records' => $this->employee->immigrationRecords()->orderByDesc('expiry_date')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="add" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3">
            <input type="text" wire:model="documentType" placeholder="Document type (e.g. Passport)" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="documentNumber" placeholder="Document number" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <select wire:model="status" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="valid">Valid</option>
                <option value="expiring_soon">Expiring soon</option>
                <option value="expired">Expired</option>
            </select>
            <div>
                <label class="mb-1 block text-xs text-text-muted">Issue date</label>
                <input type="date" wire:model="issueDate" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1 block text-xs text-text-muted">Expiry date</label>
                <input type="date" wire:model="expiryDate" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1 block text-xs text-text-muted">Review date</label>
                <input type="date" wire:model="reviewDate" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <button type="submit" class="self-start rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add record</button>
        </form>
        @error('documentType') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('documentNumber') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="divide-y divide-border">
            @foreach($records as $r)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="font-medium text-text">{{ $r->document_type }}</span>
                        <span class="font-mono text-text-muted">{{ $r->document_number }}</span>
                        @if($r->expiry_date) <span class="text-text-muted">· expires {{ $r->expiry_date->format('j M Y') }}</span> @endif
                        <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ str_replace('_', ' ', $r->status) }}</span>
                    </div>
                    <button wire:click="delete({{ $r->id }})" wire:confirm="Remove this immigration record?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($records->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No immigration records yet.</div>
            @endif
        </div>
    </section>
</div>
