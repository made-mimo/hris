<?php

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Livewire\Component;

/** Spec C1: "Reporting: leave balance and usage reports... plus a new year-end carryover/forfeiture report." */
new class extends Component
{
    public string $tab = 'balance';

    public ?int $leaveTypeId = null;

    public function with(): array
    {
        $types = LeaveType::orderBy('sort_order')->get();

        $balanceRows = collect();
        foreach (Employee::orderBy('last_name')->get() as $employee) {
            foreach ($types as $type) {
                if ($this->leaveTypeId && $type->id !== $this->leaveTypeId) {
                    continue;
                }

                $balance = $employee->leaveBalance($type);

                // Spec: leave types flagged exclude_from_reports_if_unentitled
                // drop out of the report entirely for an employee who has no
                // entitlement to them at all (e.g. someone not yet eligible).
                if ($type->exclude_from_reports_if_unentitled && $balance['entitled'] <= 0) {
                    continue;
                }

                $balanceRows->push([
                    'employee' => $employee,
                    'type' => $type,
                    'balance' => $balance,
                ]);
            }
        }

        $carryoverRows = LeaveEntitlement::where('batch_type', 'carried_over')
            ->with(['employee', 'leaveType'])
            ->orderByDesc('year')
            ->get()
            ->map(function (LeaveEntitlement $batch) {
                $consumed = $batch->consumedDays();
                $expired = today()->gt($batch->drawDownDeadline());

                return [
                    'batch' => $batch,
                    'consumed' => $consumed,
                    'forfeited' => $expired ? max(0, (float) $batch->entitled_days - $consumed) : null,
                ];
            });

        $usageByType = $balanceRows
            ->groupBy(fn (array $r) => $r['type']->name)
            ->map(fn ($rows) => round($rows->sum(fn (array $r) => $r['balance']['used']), 1));

        return [
            'types' => $types,
            'balanceRows' => $balanceRows,
            'carryoverRows' => $carryoverRows,
            'usageByType' => $usageByType,
        ];
    }
};
?>

<div>
    <div class="mb-4 flex flex-wrap gap-1.5 border-b border-border">
        @foreach(['balance' => 'Balance & Usage', 'carryover' => 'Carryover & Forfeiture'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                class="rounded-t-sm border-b-2 px-3.5 py-2.5 text-sm font-semibold transition-colors {{ $tab === $key ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($tab === 'balance')
        <section class="mb-4 rounded-md border border-border bg-surface p-4 shadow-sm">
            <h3 class="mb-3 font-display text-sm font-bold text-text">Days used by leave type</h3>
            @if($usageByType->isNotEmpty())
                <x-chart-canvas
                    id="leave-usage-by-type"
                    type="bar"
                    :labels="$usageByType->keys()->values()->all()"
                    :datasets="[['label' => 'Days used', 'data' => $usageByType->values()->all(), 'backgroundColor' => '#D9251E']]"
                    :height="220"
                />
            @else
                <p class="text-sm text-text-muted">No usage to chart yet.</p>
            @endif
        </section>
        <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
            <div class="border-b border-border p-4">
                <select wire:model.live="leaveTypeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">All leave types</option>
                    @foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                        <th class="px-4 py-2.5">Employee</th>
                        <th class="px-4 py-2.5">Leave type</th>
                        <th class="px-4 py-2.5">Entitled</th>
                        <th class="px-4 py-2.5">Used</th>
                        <th class="px-4 py-2.5">Available</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($balanceRows as $row)
                        <tr class="border-b border-border last:border-0">
                            <td class="px-4 py-2.5 text-text">{{ $row['employee']->fullName() }}</td>
                            <td class="px-4 py-2.5 text-text-muted">{{ $row['type']->name }}</td>
                            <td class="px-4 py-2.5 font-mono text-text">{{ number_format($row['balance']['entitled'], 1) }}</td>
                            <td class="px-4 py-2.5 font-mono text-text">{{ number_format($row['balance']['used'], 1) }}</td>
                            <td class="px-4 py-2.5 font-mono font-semibold text-text">{{ number_format($row['balance']['available'], 1) }}</td>
                        </tr>
                    @endforeach
                    @if($balanceRows->isEmpty())
                        <tr><td colspan="5" class="px-4 py-6 text-center text-text-muted">No entitlements to report.</td></tr>
                    @endif
                </tbody>
            </table>
        </section>
    @else
        <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
            <div class="border-b border-border p-4 text-xs text-text-muted">Carried Over batches — capped at each leave type's carryover cap, effective 1 Jan–31 Mar. "Forfeited" is only known once the batch's window has closed.</div>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                        <th class="px-4 py-2.5">Employee</th>
                        <th class="px-4 py-2.5">Leave type</th>
                        <th class="px-4 py-2.5">Year</th>
                        <th class="px-4 py-2.5">Carried over</th>
                        <th class="px-4 py-2.5">Consumed</th>
                        <th class="px-4 py-2.5">Forfeited</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carryoverRows as $row)
                        <tr class="border-b border-border last:border-0">
                            <td class="px-4 py-2.5 text-text">{{ $row['batch']->employee->fullName() }}</td>
                            <td class="px-4 py-2.5 text-text-muted">{{ $row['batch']->leaveType->name }}</td>
                            <td class="px-4 py-2.5 font-mono text-text">{{ $row['batch']->year }}</td>
                            <td class="px-4 py-2.5 font-mono text-text">{{ number_format($row['batch']->entitled_days, 1) }}</td>
                            <td class="px-4 py-2.5 font-mono text-text">{{ number_format($row['consumed'], 1) }}</td>
                            <td class="px-4 py-2.5 font-mono text-text">{{ $row['forfeited'] === null ? '—' : number_format($row['forfeited'], 1) }}</td>
                        </tr>
                    @endforeach
                    @if($carryoverRows->isEmpty())
                        <tr><td colspan="6" class="px-4 py-6 text-center text-text-muted">No carryover batches yet.</td></tr>
                    @endif
                </tbody>
            </table>
        </section>
    @endif
</div>
