<?php

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\PayGrade;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    public string $name = '';

    public ?int $payGradeId = null;

    public string $amount = '';

    public string $currency = 'NGN';

    public string $payPeriod = 'monthly';

    public string $bankName = '';

    public string $bankAccountNumber = '';

    public string $bankAccountName = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function add(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payPeriod' => ['required', 'in:monthly,weekly,biweekly,annual'],
            'payGradeId' => ['nullable', 'exists:pay_grades,id'],
        ]);

        $this->employee->compensations()->create([
            'pay_grade_id' => $this->payGradeId,
            'name' => $this->name,
            'amount' => $this->amount,
            'currency' => strtoupper($this->currency),
            'pay_period' => $this->payPeriod,
            'bank_name' => $this->bankName ?: null,
            'bank_account_number' => $this->bankAccountNumber ?: null,
            'bank_account_name' => $this->bankAccountName ?: null,
        ]);

        $this->reset('name', 'payGradeId', 'amount', 'bankName', 'bankAccountNumber', 'bankAccountName');
        $this->currency = 'NGN';
        $this->payPeriod = 'monthly';
        session()->flash('status', 'Compensation line added.');
    }

    public function toggleActive(int $id): void
    {
        $line = EmployeeCompensation::where('employee_id', $this->employee->id)->findOrFail($id);
        $line->update(['is_active' => ! $line->is_active]);
    }

    public function delete(int $id): void
    {
        EmployeeCompensation::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
        session()->flash('status', 'Compensation line removed.');
    }

    public function with(): array
    {
        return [
            'lines' => $this->employee->compensations()->with('payGrade')->orderByDesc('is_active')->orderByDesc('created_at')->get(),
            'payGrades' => PayGrade::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="add" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            <input type="text" wire:model="name" placeholder="Name (e.g. Base salary)" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <select wire:model="payGradeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">— pay grade —</option>
                @foreach($payGrades as $pg)
                    <option value="{{ $pg->id }}">{{ $pg->name }}</option>
                @endforeach
            </select>
            <input type="number" step="0.01" wire:model="amount" placeholder="Amount" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="currency" maxlength="3" placeholder="NGN" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm uppercase text-text outline-none focus:border-primary">
            <select wire:model="payPeriod" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
                <option value="biweekly">Biweekly</option>
                <option value="annual">Annual</option>
            </select>
            <input type="text" wire:model="bankName" placeholder="Bank name (optional)" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="bankAccountNumber" placeholder="Account number (optional)" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="bankAccountName" placeholder="Account name (optional)" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="self-start rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('name') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('amount') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="divide-y divide-border">
            @foreach($lines as $line)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="font-medium text-text">{{ $line->name }}</span>
                        <span class="font-mono text-text-muted">{{ $line->currency }} {{ number_format((float) $line->amount, 2) }}</span>
                        <span class="text-text-muted">/ {{ $line->pay_period }}</span>
                        @if($line->payGrade) <span class="text-text-muted">· {{ $line->payGrade->name }}</span> @endif
                        @if(! $line->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="toggleActive({{ $line->id }})" class="text-xs font-semibold text-primary">{{ $line->is_active ? 'Deactivate' : 'Activate' }}</button>
                        <button wire:click="delete({{ $line->id }})" wire:confirm="Remove this compensation line?" class="text-xs font-semibold text-danger">Delete</button>
                    </div>
                </div>
            @endforeach
            @if($lines->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No compensation lines yet.</div>
            @endif
        </div>
    </section>
</div>
