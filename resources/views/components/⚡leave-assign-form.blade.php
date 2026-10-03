<?php

use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\LeaveRequestService;
use App\Services\PermissionService;
use App\Services\NotificationService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Spec C1: "admin/supervisor Assign (books leave directly, bypassing approval, optionally bypassing the balance check)." Scoped to whichever employees the caller's own leave_requests data-group grant covers (all / self + subordinates) — the same scope approvals-board's queue already uses. */
new class extends Component
{
    public ?int $employeeId = null;

    public ?int $leaveTypeId = null;

    public string $startDate = '';

    public string $endDate = '';

    public string $duration = 'full';

    public string $reason = '';

    public bool $bypassBalance = false;

    public function mount(): void
    {
        $this->leaveTypeId = LeaveType::orderBy('sort_order')->first()?->id;
        $this->startDate = now()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function submit(NotificationService $notifications): void
    {
        $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'leaveTypeId' => ['required', 'exists:leave_types,id'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        $employee = Employee::findOrFail($this->employeeId);

        if (! in_array($employee->id, $this->assignableEmployeeIds(), true)) {
            abort(403, "You don't have access to assign leave for this employee.");
        }

        $type = LeaveType::findOrFail($this->leaveTypeId);

        try {
            $request = app(LeaveRequestService::class)->assign(
                $employee,
                $type,
                \Carbon\Carbon::parse($this->startDate),
                \Carbon\Carbon::parse($this->endDate),
                $this->duration,
                $this->reason ?: null,
                $this->bypassBalance,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        if ($employee->user) {
            $notifications->notify(
                $employee->user,
                'leave.assigned',
                'Leave assigned',
                "{$type->name} leave for {$request->days} day(s) has been booked for you.",
                '/leave/apply',
                'Leave'
            );
        }

        session()->flash('status', "{$request->days} day(s) of {$type->name} leave assigned to {$employee->fullName()}.");
        $this->reset('employeeId', 'startDate', 'endDate', 'reason', 'bypassBalance');
        $this->startDate = now()->toDateString();
        $this->endDate = now()->toDateString();
    }

    protected function assignableEmployeeIds(): array
    {
        $user = auth()->user();
        $scope = app(PermissionService::class)->scopeFor($user, 'leave_requests');
        $employee = $user->employee;

        return match ($scope) {
            'all' => Employee::pluck('id')->all(),
            'self_subordinates' => $employee ? [$employee->id, ...$employee->subordinates()->pluck('id')->all()] : [],
            default => [],
        };
    }

    public function with(): array
    {
        return [
            'employees' => Employee::whereIn('id', $this->assignableEmployeeIds())->orderBy('last_name')->get(),
            'types' => LeaveType::orderBy('sort_order')->get(),
        ];
    }
};
?>

<div class="grid grid-2" style="align-items:start;">
    <form wire:submit="submit" class="card">
        @if(session('status'))
            <div class="pill pill-success" style="margin-bottom:16px;padding:10px 14px;">{{ session('status') }}</div>
        @endif

        <div class="field">
            <label for="employeeId">Employee</label>
            <select id="employeeId" wire:model="employeeId">
                <option value="">— select —</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}">{{ $e->fullName() }}</option>
                @endforeach
            </select>
            @error('employeeId') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="leaveTypeId">Leave type</label>
            <select id="leaveTypeId" wire:model="leaveTypeId">
                @foreach($types as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-2">
            <div class="field" style="margin:0;">
                <label for="startDate">From</label>
                <input id="startDate" type="date" wire:model="startDate">
            </div>
            <div class="field" style="margin:0;">
                <label for="endDate">To</label>
                <input id="endDate" type="date" wire:model="endDate">
                @error('endDate') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
                @error('startDate') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="field">
            <label for="duration">Duration per day</label>
            <select id="duration" wire:model="duration">
                <option value="full">Full day</option>
                <option value="am">Half day · AM</option>
                <option value="pm">Half day · PM</option>
                <option value="time">Specific time</option>
            </select>
        </div>

        <div class="field">
            <label for="reason">Reason <span class="text-muted" style="font-weight:400;">(optional)</span></label>
            <textarea id="reason" rows="2" wire:model="reason"></textarea>
        </div>

        <label style="display:flex;align-items:center;gap:8px;font-size:var(--fs-sm);margin-bottom:16px;">
            <input type="checkbox" wire:model="bypassBalance"> Bypass the balance check (allow booking beyond available entitlement)
        </label>

        <button type="submit" class="btn btn-primary">Assign leave</button>
    </form>

    <section class="card">
        <h2 style="margin-bottom:10px;">How Assign differs from Apply</h2>
        <ul class="text-muted" style="font-size:var(--fs-sm);line-height:1.7;padding-left:18px;">
            <li>Books leave directly as approved — no Line Manager/HR approval step.</li>
            <li>Entitlement is consumed immediately, the same FIFO/expiry-aware rule Apply uses.</li>
            <li>The balance check can be bypassed entirely for a specific booking, e.g. an exceptional grant.</li>
            <li>Still blocked by overlap prevention — an employee can't have two overlapping leave periods either way.</li>
        </ul>
    </section>
</div>
