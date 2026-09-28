<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Spec C3: punch history + the three independently toggleable permissions.
 * "Employee" here means whichever record is being viewed/managed — the
 * signed-in user's own by default, or (if permitted) a subordinate's, or
 * (if Admin) anyone's.
 */
new class extends Component
{
    public ?int $employeeId = null;

    public string $backdateTime = '';

    public ?int $editingId = null;

    public string $editPunchIn = '';

    public string $editPunchOut = '';

    public function mount(): void
    {
        $this->employeeId = auth()->user()->employee->id;
    }

    protected function isSelf(): bool
    {
        return $this->employeeId === auth()->user()->employee->id;
    }

    protected function canManageTarget(AttendanceService $service): bool
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isSelf()) {
            return $service->canEditOwnRecords($user);
        }

        $target = Employee::find($this->employeeId);

        return $target && $target->supervisor_id === $user->employee?->id && $service->canSupervisorProxyOrEdit($user);
    }

    public function punchInFor(AttendanceService $service): void
    {
        $user = auth()->user();
        $target = Employee::findOrFail($this->employeeId);
        $isProxy = ! $this->isSelf();

        if ($isProxy && ! ($user->isAdmin() || $service->canSupervisorProxyOrEdit($user))) {
            abort(403, "You don't have permission to proxy-punch for this employee.");
        }

        $canBackdate = $user->isAdmin() || $isProxy || $service->canBackdateOwnPunch();
        $atTime = ($canBackdate && $this->backdateTime) ? Carbon::parse($this->backdateTime) : null;

        try {
            $service->punchIn($target, $user, $atTime);
            session()->flash('status', 'Punched in.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['punch'][0]);
        }

        $this->reset('backdateTime');
    }

    public function punchOutFor(AttendanceService $service): void
    {
        $user = auth()->user();
        $target = Employee::findOrFail($this->employeeId);
        $isProxy = ! $this->isSelf();

        if ($isProxy && ! ($user->isAdmin() || $service->canSupervisorProxyOrEdit($user))) {
            abort(403, "You don't have permission to proxy-punch for this employee.");
        }

        try {
            $service->punchOut($target, $user);
            session()->flash('status', 'Punched out.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['punch'][0]);
        }
    }

    public function startEdit(int $id): void
    {
        $record = AttendanceRecord::where('employee_id', $this->employeeId)->findOrFail($id);
        $this->editingId = $id;
        $this->editPunchIn = $record->punch_in_at_utc->format('Y-m-d\TH:i');
        $this->editPunchOut = $record->punch_out_at_utc?->format('Y-m-d\TH:i') ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function saveEdit(AttendanceService $service): void
    {
        abort_unless($this->canManageTarget($service), 403);

        $record = AttendanceRecord::where('employee_id', $this->employeeId)->findOrFail($this->editingId);
        $start = Carbon::parse($this->editPunchIn);
        $end = $this->editPunchOut ? Carbon::parse($this->editPunchOut) : null;

        try {
            $service->assertNoOverlap($record->employee, $start, $end, excludeId: $record->id);
        } catch (ValidationException $e) {
            $this->addError('editPunchIn', $e->errors()['punch'][0]);

            return;
        }

        $record->update([
            'punch_in_at_utc' => $start,
            'punch_in_at_local' => $start,
            'punch_out_at_utc' => $end,
            'punch_out_at_local' => $end,
        ]);

        $this->editingId = null;
        session()->flash('status', 'Record updated.');
    }

    public function deleteRecord(int $id, AttendanceService $service): void
    {
        abort_unless($this->canManageTarget($service), 403);

        AttendanceRecord::where('employee_id', $this->employeeId)->findOrFail($id)->delete();
        session()->flash('status', 'Record deleted.');
    }

    public function with(AttendanceService $service): array
    {
        $user = auth()->user();
        $me = $user->employee;

        $viewableEmployees = $user->isAdmin()
            ? Employee::orderBy('last_name')->get()
            : collect([$me])->merge($me->subordinates()->orderBy('last_name')->get());

        $target = Employee::find($this->employeeId) ?? $me;

        return [
            'viewableEmployees' => $viewableEmployees,
            'target' => $target,
            'currentPunch' => $target->currentPunch(),
            'records' => $target->attendanceRecords()->orderByDesc('punch_in_at_utc')->take(30)->get(),
            'canManage' => $this->canManageTarget($service),
            'canBackdate' => $user->isAdmin() || ! $this->isSelf() || $service->canBackdateOwnPunch(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-danger/10 px-3.5 py-2.5 text-xs font-semibold text-danger">{{ session('error') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        @if($viewableEmployees->count() > 1)
            <div class="mb-4">
                <label class="mb-1.5 block text-xs font-semibold text-text">Viewing</label>
                <select wire:model.live="employeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @foreach($viewableEmployees as $e)
                        <option value="{{ $e->id }}">{{ $e->fullName() }}{{ $e->id === auth()->user()->employee->id ? ' (you)' : '' }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <span class="rounded-pill bg-text-faint/15 px-3 py-1.5 text-xs font-semibold text-text-muted">
                {{ $currentPunch ? 'Clocked in since '.$currentPunch->punch_in_at_local->format('j M, H:i') : 'Not clocked in' }}
            </span>

            @if($canBackdate && ! $currentPunch)
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Punch-in time <span class="text-text-muted">(optional — defaults to now)</span></label>
                    <input type="datetime-local" wire:model="backdateTime" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
            @endif

            @if($currentPunch)
                <button wire:click="punchOutFor" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Punch out</button>
            @else
                <button wire:click="punchInFor" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Punch in</button>
            @endif
        </div>

        <div class="divide-y divide-border">
            @foreach($records as $record)
                <div class="py-2.5">
                    @if($editingId === $record->id)
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-text-muted">Punch in</label>
                                <input type="datetime-local" wire:model="editPunchIn" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-text-muted">Punch out</label>
                                <input type="datetime-local" wire:model="editPunchOut" class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                            </div>
                            <button wire:click="saveEdit" class="text-xs font-semibold text-primary">Save</button>
                            <button wire:click="cancelEdit" class="text-xs font-semibold text-text-muted">Cancel</button>
                        </div>
                        @error('editPunchIn') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    @else
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-text">
                                {{ $record->punch_in_at_local->format(\App\Support\Dates::DATE_TIME) }}
                                –
                                {{ $record->punch_out_at_local?->format('H:i') ?? 'still clocked in' }}
                                @if($record->durationHours() !== null)
                                    <span class="font-mono text-text-muted">({{ $record->durationHours() }}h)</span>
                                @endif
                                @if($record->is_proxy_punch) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Proxy</span> @endif
                            </span>
                            @if($canManage)
                                <span class="flex gap-3">
                                    <button wire:click="startEdit({{ $record->id }})" class="text-xs font-semibold text-primary">Edit</button>
                                    <button wire:click="deleteRecord({{ $record->id }})" wire:confirm="Delete this attendance record?" class="text-xs font-semibold text-danger">Delete</button>
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
            @if($records->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No attendance records yet.</div>
            @endif
        </div>
    </section>
</div>
