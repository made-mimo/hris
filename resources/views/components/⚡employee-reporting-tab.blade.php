<?php

use App\Models\Employee;
use App\Models\EmployeeSupervisor;
use Livewire\Component;

/**
 * Spec B2: "an employee may have multiple supervisors and multiple
 * subordinates simultaneously, each relationship additionally tagged with a
 * reporting method." Each employee manages their OWN supervisors here (the
 * natural direction — "who do I report to"); subordinates are the reverse
 * of another employee's own choice, so they're shown read-only, edited from
 * that subordinate's own Reporting tab instead of duplicating the same edit
 * surface in two places.
 */
new class extends Component
{
    public Employee $employee;

    public ?int $supervisorId = null;

    public string $reportingMethod = 'direct';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function addSupervisor(): void
    {
        $this->validate([
            'supervisorId' => ['required', 'exists:employees,id'],
            'reportingMethod' => ['required', 'in:direct,dotted_line'],
        ]);

        if ($this->supervisorId === $this->employee->id) {
            $this->addError('supervisorId', 'An employee cannot be their own supervisor.');

            return;
        }

        if ($this->wouldCreateCycle($this->supervisorId)) {
            $this->addError('supervisorId', 'That would create a reporting-line cycle.');

            return;
        }

        EmployeeSupervisor::updateOrCreate(
            ['employee_id' => $this->employee->id, 'supervisor_id' => $this->supervisorId],
            ['reporting_method' => $this->reportingMethod]
        );

        $this->employee->syncPrimarySupervisor();
        $this->reset('supervisorId');
        $this->reportingMethod = 'direct';
        session()->flash('status', 'Supervisor added.');
    }

    /** Walk upward from the candidate supervisor — if we ever reach $this->employee, adding the edge would close a loop. */
    protected function wouldCreateCycle(int $candidateSupervisorId): bool
    {
        $visited = [];
        $queue = [$candidateSupervisorId];

        while ($queue) {
            $current = array_shift($queue);
            if ($current === $this->employee->id) {
                return true;
            }
            if (in_array($current, $visited, true)) {
                continue;
            }
            $visited[] = $current;
            $queue = array_merge($queue, EmployeeSupervisor::where('employee_id', $current)->pluck('supervisor_id')->all());
        }

        return false;
    }

    public function removeSupervisor(int $linkId): void
    {
        EmployeeSupervisor::where('employee_id', $this->employee->id)->findOrFail($linkId)->delete();
        $this->employee->syncPrimarySupervisor();
        session()->flash('status', 'Supervisor removed.');
    }

    public function with(): array
    {
        return [
            'supervisorLinks' => $this->employee->supervisorLinks()->with('supervisor')->get(),
            'subordinateLinks' => $this->employee->subordinateLinks()->with('employee')->get(),
            'candidates' => Employee::where('id', '!=', $this->employee->id)->orderBy('last_name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Reports to</h2>
        <form wire:submit="addSupervisor" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <label class="mb-1.5 block text-xs font-semibold text-text">Supervisor</label>
                <select wire:model="supervisorId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">— select —</option>
                    @foreach($candidates as $c)
                        <option value="{{ $c->id }}">{{ $c->fullName() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Reporting method</label>
                <select wire:model="reportingMethod" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="direct">Direct</option>
                    <option value="dotted_line">Dotted-line / matrix</option>
                </select>
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('supervisorId') <div class="mb-3 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="divide-y divide-border">
            @foreach($supervisorLinks as $link)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <span>{{ $link->supervisor->fullName() }} <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ $link->reporting_method === 'direct' ? 'Direct' : 'Dotted-line' }}</span></span>
                    <button wire:click="removeSupervisor({{ $link->id }})" wire:confirm="Remove this supervisor?" class="text-xs font-semibold text-danger">Remove</button>
                </div>
            @endforeach
            @if($supervisorLinks->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No supervisors yet.</div>
            @endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Direct &amp; dotted-line reports</h2>
        <div class="mb-3 text-xs text-text-muted">Read-only here — each employee manages their own reporting line from their own Reporting tab.</div>
        <div class="divide-y divide-border">
            @foreach($subordinateLinks as $link)
                <div class="flex items-center gap-2.5 py-2.5 text-sm">
                    <span>{{ $link->employee->fullName() }}</span>
                    <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ $link->reporting_method === 'direct' ? 'Direct' : 'Dotted-line' }}</span>
                </div>
            @endforeach
            @if($subordinateLinks->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No direct or dotted-line reports.</div>
            @endif
        </div>
    </section>
</div>
