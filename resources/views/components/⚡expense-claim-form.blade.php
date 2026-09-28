<?php

use App\Models\ClaimEvent;
use App\Models\ExpenseType;
use App\Services\ExpenseClaimService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $claimEventId = null;
    public string $currency = 'NGN';
    public array $lines = [];
    public $receipt = null;

    public function mount(): void
    {
        $this->claimEventId = ClaimEvent::where('active', true)->first()?->id;
        $airId = ExpenseType::where('name', 'Air travel')->value('id');
        $this->lines = [
            ['type_id' => $airId, 'date' => now()->subDays(3)->toDateString(), 'note' => '', 'amount' => null],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['type_id' => ExpenseType::first()?->id, 'date' => now()->toDateString(), 'note' => '', 'amount' => null];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function getTotalProperty(): float
    {
        return collect($this->lines)->sum(fn ($l) => (float) ($l['amount'] ?? 0));
    }

    public function submit(ExpenseClaimService $claims): void
    {
        $this->validate([
            'claimEventId' => ['required', 'exists:claim_events,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.type_id' => ['required', 'exists:expense_types,id'],
            'lines.*.date' => ['required', 'date'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'receipt' => ['nullable', 'file', 'max:10240'],
        ]);

        $me = auth()->user()->employee;

        $claim = $claims->submit($me, $this->claimEventId, $this->currency, $this->lines);

        if ($this->receipt) {
            $claim->addMedia($this->receipt->getRealPath())->usingName($this->receipt->getClientOriginalName())->toMediaCollection('receipts');
        }

        session()->flash('status', "Claim {$claim->reference} submitted for approval.");
        $this->redirectRoute('home', navigate: true);
    }

    public function with(): array
    {
        return [
            'claimEvents' => ClaimEvent::where('active', true)->get(),
            'expenseTypes' => ExpenseType::where('active', true)->get(),
        ];
    }
};
?>

<form wire:submit="submit" class="grid grid-3" style="align-items:start;">
    <div class="col-span-2" style="display:flex;flex-direction:column;gap:18px;">

        <section class="card">
            <div class="grid grid-2">
                <div class="field" style="margin:0;">
                    <label for="event">Claim event</label>
                    <select id="event" wire:model="claimEventId">
                        @foreach($claimEvents as $e)
                            <option value="{{ $e->id }}">{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0;">
                    <label for="cur">Currency</label>
                    <select id="cur" wire:model="currency">
                        <option value="NGN">NGN — Naira</option>
                        <option value="USD">USD — US Dollar</option>
                        <option value="GBP">GBP — Pound Sterling</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="card" style="padding:0;overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px 12px;">
                <h2>Expenses</h2>
                <button type="button" wire:click="addLine" class="btn btn-outline btn-sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>Add expense
                </button>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th style="width:170px;">Type</th><th style="width:130px;">Date</th><th>Note</th><th style="width:130px;text-align:right;">Amount</th><th style="width:44px;"></th></tr></thead>
                    <tbody>
                        @foreach($lines as $i => $line)
                            @php $type = $expenseTypes->firstWhere('id', (int) ($line['type_id'] ?? 0)); @endphp
                            <tr wire:key="line-{{ $i }}">
                                <td><select wire:model="lines.{{ $i }}.type_id" style="border:none;background:none;font-family:inherit;font-size:14px;font-weight:600;">
                                    @foreach($expenseTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                                </select></td>
                                <td><input type="date" wire:model="lines.{{ $i }}.date" style="border:none;background:none;font-family:inherit;font-size:13px;padding:0;"></td>
                                <td><input type="text" wire:model="lines.{{ $i }}.note" placeholder="Note" style="border:none;background:none;font-family:inherit;font-size:13px;padding:0;width:100%;"></td>
                                <td><input type="number" step="0.01" wire:model.live="lines.{{ $i }}.amount" placeholder="0.00" style="border:none;background:none;font-family:var(--font-mono);font-size:14px;font-weight:600;text-align:right;padding:0;width:100%;"></td>
                                <td>
                                    <button type="button" wire:click="removeLine({{ $i }})" aria-label="Remove expense" class="icon-btn" style="background:transparent;border:none;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"></path></svg>
                                    </button>
                                </td>
                            </tr>
                            @if($type?->default_cap && ($line['amount'] ?? 0) > $type->default_cap)
                                <tr wire:key="flag-{{ $i }}"><td colspan="5" style="padding:0 22px 12px;">
                                    <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--color-warning-light);border-radius:8px;">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--color-warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 3 2 20h20z"></path><path d="M12 10v4M12 17h.01"></path></svg>
                                        <span style="font-size:12.5px;color:var(--color-warning);"><strong>Above cap</strong> · ₦{{ number_format($type->default_cap) }}</span>
                                    </div>
                                </td></tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:24px;padding:14px 22px;background:var(--color-bg);font-size:14px;">
                <span class="text-muted">{{ count($lines) }} items</span>
                <span style="font-weight:600;">Subtotal <span class="font-mono" style="margin-left:10px;">₦{{ number_format($this->total, 2) }}</span></span>
            </div>
        </section>

        <section class="card">
            <label for="receipt" style="font-weight:600;font-size:13.5px;display:block;margin-bottom:8px;">Receipt / supporting document <span class="text-muted" style="font-weight:400;">(optional)</span></label>
            <input id="receipt" type="file" wire:model="receipt">
            @error('receipt') <div class="hint" style="color:var(--color-danger);margin-top:6px;">{{ $message }}</div> @enderror
        </section>

        <div class="hint">Need funds ahead of the trip? <a href="{{ route('claims.travel-advance') }}">Request a Travel Advance</a> against this claim event first.</div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px;">
        <section class="card-dark">
            <div class="text-faint" style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">Claim total</div>
            <div class="font-mono" style="font-size:32px;font-weight:600;margin-top:6px;color:#fff;">₦{{ number_format($this->total, 2) }}</div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:18px;">Submit claim
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
            </button>
            <div class="text-faint" style="font-size:12px;margin-top:10px;text-align:center;">Expenses lock once submitted.</div>
        </section>

        <section class="card">
            <h2 style="margin-bottom:16px;">Approval route</h2>
            <ol class="timeline">
                <li class="timeline-step">
                    <div class="timeline-rail"><span class="timeline-dot is-current"></span><span class="timeline-line"></span></div>
                    <div class="timeline-body"><div class="timeline-title">Line Manager</div><div class="timeline-note">{{ auth()->user()->employee->supervisor?->fullName() ?? '—' }}</div></div>
                </li>
                <li class="timeline-step">
                    <div class="timeline-rail"><span class="timeline-dot"></span></div>
                    <div class="timeline-body"><div class="timeline-title">HR &amp; Admin</div><div class="timeline-note">Review and route to Finance</div></div>
                </li>
            </ol>
        </section>
    </div>
</form>
