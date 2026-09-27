<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Services\NotificationService;
use Livewire\Component;

new class extends Component
{
    public ?int $leaveTypeId = null;
    public string $duration = 'full';
    public string $startDate;
    public string $endDate;
    public ?int $relieverId = null;
    public string $reason = '';

    protected $durationFactors = ['full' => 1, 'am' => 0.5, 'pm' => 0.5, 'time' => 0.25];
    protected $durationLabels = ['full' => 'Full day', 'am' => 'Half day · AM', 'pm' => 'Half day · PM', 'time' => 'Specific time'];

    public function mount(): void
    {
        $this->leaveTypeId = LeaveType::orderBy('sort_order')->first()?->id;
        $this->startDate = now()->addWeek()->startOfWeek()->toDateString();
        $this->endDate = now()->addWeek()->startOfWeek()->addDays(4)->toDateString();
    }

    public function pickType(int $id): void
    {
        $this->leaveTypeId = $id;
    }

    public function pickDuration(string $key): void
    {
        $this->duration = $key;
    }

    /** Business-day count in the selected range × the duration factor — a simplified stand-in for
     *  the spec's per-day work-shift/holiday-aware calculation (Section C1). */
    public function getRequestDaysProperty(): float
    {
        $start = \Carbon\Carbon::parse($this->startDate);
        $end = \Carbon\Carbon::parse($this->endDate);

        if ($end->lt($start)) {
            return 0;
        }

        $businessDays = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (! $d->isWeekend()) {
                $businessDays++;
            }
        }

        return $this->duration === 'full' ? $businessDays : $businessDays * $this->durationFactors[$this->duration];
    }

    public function submit(NotificationService $notifications): void
    {
        $this->validate([
            'leaveTypeId' => ['required', 'exists:leave_types,id'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        $me = auth()->user()->employee;
        $type = LeaveType::findOrFail($this->leaveTypeId);
        $days = $this->requestDays;

        if ($days <= 0) {
            $this->addError('endDate', 'That range has no working days in it.');

            return;
        }

        $balance = $me->leaveBalance($type);
        if ($days > $balance['available']) {
            $this->addError('endDate', "Only {$balance['available']} days available for {$type->name} leave.");

            return;
        }

        // Routes into the workflow engine's matrix (App\Services\WorkflowEngine,
        // WorkflowSeeder) at whichever state the "supervisor" transitions
        // actually start from. An employee with no supervisor_id has no one
        // who could ever act on "pending_manager", so they skip straight to
        // the HR queue — an explicit routing rule at submission time, not a
        // bypass of the matrix itself (see PLAN.md §4.7).
        $hasSupervisor = (bool) $me->supervisor_id;

        $request = LeaveRequest::create([
            'reference' => 'LV-'.now()->year.'-'.str_pad((string) (LeaveRequest::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'employee_id' => $me->id,
            'leave_type_id' => $type->id,
            'reliever_employee_id' => $this->relieverId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'duration_type' => $this->duration,
            'days' => $days,
            'reason' => $this->reason,
            'status' => $hasSupervisor ? 'pending_manager' : 'pending_hr',
            'manager_approved_at' => $hasSupervisor ? null : now(),
        ]);

        // Spec's "every status change (apply/approve/reject/cancel/assign)
        // triggers... notification" — submission itself, not just later
        // transitions (which WorkflowEngine::apply() already covers).
        $recipients = $hasSupervisor
            ? ($me->supervisor?->user ? [$me->supervisor->user] : [])
            : (Role::where('slug', 'hr_admin')->first()?->users ?? []);

        foreach ($recipients as $recipient) {
            $notifications->notify(
                $recipient,
                'leave.submitted',
                'New leave request',
                "{$me->fullName()} requested {$request->days} day(s) of {$type->name} leave.",
                '/approvals',
                'Leave'
            );
        }

        session()->flash('status', $hasSupervisor
            ? 'Leave request submitted to your line manager for approval.'
            : 'Leave request submitted and sent to HR for final approval.');
        $this->redirectRoute('home', navigate: true);
    }

    public function with(): array
    {
        $me = auth()->user()->employee;
        $types = LeaveType::orderBy('sort_order')->get()->map(function ($t) use ($me) {
            $b = $me->leaveBalance($t);

            return ['id' => $t->id, 'name' => $t->name, 'available' => $b['available']];
        });

        $selectedType = LeaveType::find($this->leaveTypeId);
        $balance = $selectedType ? $me->leaveBalance($selectedType) : ['entitled' => 0, 'used' => 0, 'available' => 0];
        $afterDays = max(0, $balance['available'] - $this->requestDays);

        $colleagues = Employee::where('sub_unit_id', $me->sub_unit_id)->where('id', '!=', $me->id)->with('jobTitle')->get();

        return [
            'me' => $me,
            'types' => $types,
            'durationLabels' => $this->durationLabels,
            'selectedType' => $selectedType,
            'balance' => $balance,
            'afterDays' => $afterDays,
            'colleagues' => $colleagues,
        ];
    }
};
?>

<div class="grid grid-3" style="align-items:start;">
    <form wire:submit="submit" class="card col-span-2" style="padding:0;">
        <div style="padding:22px 24px;display:flex;flex-direction:column;gap:22px;">

            <fieldset style="border:none;margin:0;padding:0;">
                <legend style="font-size:13px;font-weight:600;margin-bottom:10px;padding:0;">Leave type</legend>
                <div class="grid grid-4">
                    @foreach($types as $t)
                        <button type="button" wire:click="pickType({{ $t['id'] }})" wire:key="type-{{ $t['id'] }}" class="option-card {{ $leaveTypeId === $t['id'] ? 'is-active' : '' }}">
                            <span class="option-card-title">{{ $t['name'] }}</span>
                            <span class="option-card-hint">{{ rtrim(rtrim(number_format($t['available'],1),'0'),'.') }} days available</span>
                        </button>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid grid-2">
                <div class="field" style="margin:0;">
                    <label for="from">From</label>
                    <input id="from" type="date" wire:model.live="startDate">
                </div>
                <div class="field" style="margin:0;">
                    <label for="to">To</label>
                    <input id="to" type="date" wire:model.live="endDate">
                </div>
            </div>

            <fieldset style="border:none;margin:0;padding:0;">
                <legend style="font-size:13px;font-weight:600;margin-bottom:10px;padding:0;">Duration per day</legend>
                <div class="seg-switch">
                    @foreach($durationLabels as $key => $label)
                        <button type="button" wire:click="pickDuration('{{ $key }}')" wire:key="dur-{{ $key }}" class="seg-btn {{ $duration === $key ? 'is-active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:var(--color-text-muted);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"></path></svg>
                    <span><strong class="font-mono" style="color:var(--color-text);">{{ number_format($this->requestDays, 1) }}</strong> working days requested</span>
                </div>
            </fieldset>

            <div class="field" style="margin:0;">
                <label for="reliever">Reliever</label>
                <select id="reliever" wire:model="relieverId">
                    <option value="">— none —</option>
                    @foreach($colleagues as $c)
                        <option value="{{ $c->id }}">{{ $c->fullName() }} · {{ $c->jobTitleName() }}</option>
                    @endforeach
                </select>
                <div class="hint">Your reliever is notified as soon as you submit.</div>
            </div>

            <div class="field" style="margin:0;">
                <label for="reason">Reason <span class="text-muted" style="font-weight:400;">(optional)</span></label>
                <textarea id="reason" rows="3" wire:model="reason"></textarea>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-top:1px solid var(--color-border);background:var(--color-bg);border-radius:0 0 14px 14px;">
            <span class="text-muted" style="font-size:12.5px;">Two-stage approval: Line Manager, then HR.</span>
            <div style="display:flex;gap:10px;">
                <a href="{{ route('home') }}" wire:navigate class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Submit request
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
                </button>
            </div>
        </div>
    </form>

    <div style="display:flex;flex-direction:column;gap:18px;">
        <section class="card">
            <div class="card-header">
                <h2>{{ $selectedType?->name }} balance</h2>
                <span class="text-muted" style="font-size:12px;">Jan – Dec {{ now()->year }}</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:8px;">
                <span class="font-mono" style="font-size:30px;font-weight:600;">{{ number_format($afterDays, 1) }}</span>
                <span class="text-muted" style="font-size:13px;">days left after this request</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:13.5px;margin-top:14px;">
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Entitled</span><span class="font-mono" style="font-weight:600;">{{ number_format($balance['entitled'],1) }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Taken + scheduled</span><span class="font-mono" style="font-weight:600;">{{ number_format($balance['used'],1) }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">This request</span><span class="font-mono" style="font-weight:600;color:var(--color-primary-dark);">−{{ number_format($this->requestDays,1) }}</span></div>
            </div>
            @if($selectedType?->carries_over_at_year_end)
                <div class="hint" style="margin-top:14px;padding:10px 12px;background:var(--color-bg);border-radius:8px;">Unused {{ $selectedType->name }} leave carries into Q1 {{ now()->year + 1 }} and expires 31 March.</div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-bottom:16px;">What happens next</h2>
            <ol class="timeline">
                <li class="timeline-step">
                    <div class="timeline-rail"><span class="timeline-dot is-current">1</span><span class="timeline-line"></span></div>
                    <div class="timeline-body"><div class="timeline-title">Reliever is notified</div><div class="timeline-note">No action needed</div></div>
                </li>
                <li class="timeline-step">
                    <div class="timeline-rail"><span class="timeline-dot">2</span><span class="timeline-line"></span></div>
                    <div class="timeline-body"><div class="timeline-title">Line Manager approves</div><div class="timeline-note">{{ $me->supervisor?->fullName() ?? 'Your supervisor' }}</div></div>
                </li>
                <li class="timeline-step">
                    <div class="timeline-rail"><span class="timeline-dot">3</span><span class="timeline-line"></span></div>
                    <div class="timeline-body"><div class="timeline-title">HR &amp; Admin gives final approval</div><div class="timeline-note">Balance is re-checked at this step</div></div>
                </li>
                <li class="timeline-step">
                    <div class="timeline-rail"><span class="timeline-dot"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"></path></svg></span></div>
                    <div class="timeline-body"><div class="timeline-title">You're notified of the decision</div><div class="timeline-note">In-app notification</div></div>
                </li>
            </ol>
        </section>
    </div>
</div>
