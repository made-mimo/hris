<?php

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Role;
use App\Services\LeaveDayGeneratorService;
use App\Services\LeaveRequestService;
use App\Services\NotificationService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public ?int $leaveTypeId = null;
    public string $duration = 'full';
    public string $startDate;
    public string $endDate;
    public ?int $relieverId = null;
    public string $reason = '';

    protected $durationLabels = ['full' => 'Full day', 'am' => 'Half day · AM', 'pm' => 'Half day · PM', 'time' => 'Specific time'];

    public function mount(): void
    {
        $this->leaveTypeId = $this->visibleLeaveTypes()->first()?->id;
        $this->startDate = now()->addWeek()->startOfWeek()->toDateString();
        $this->endDate = now()->addWeek()->startOfWeek()->addDays(4)->toDateString();
    }

    /**
     * Admin backlog item 14 — a type flagged exclude_from_reports_if_
     * unentitled (e.g. Annual, Sick: accrual-based) drops out here for an
     * employee with no entitlement to it at all, the same rule Leave
     * Reports already applies. A type left unflagged (e.g. Unpaid,
     * Compassionate: available to everyone by policy, no assignment
     * needed) always shows, entitled or not.
     */
    private function visibleLeaveTypes()
    {
        $me = auth()->user()->employee;

        return LeaveType::orderBy('sort_order')->get()
            ->reject(fn (LeaveType $t) => $t->exclude_from_reports_if_unentitled && $me->leaveBalance($t)['entitled'] <= 0)
            ->values();
    }

    public function pickType(int $id): void
    {
        $this->leaveTypeId = $id;
    }

    public function pickDuration(string $key): void
    {
        $this->duration = $key;
    }

    /** Now Work-Week/Holiday-calendar-aware (LeaveCalendarService via LeaveDayGeneratorService), replacing the earlier flat "skip weekends" placeholder — see PLAN.md. */
    public function getRequestDaysProperty(): float
    {
        $start = \Carbon\Carbon::parse($this->startDate);
        $end = \Carbon\Carbon::parse($this->endDate);

        if ($end->lt($start)) {
            return 0;
        }

        return app(LeaveDayGeneratorService::class)->generate($start, $end, $this->duration)['totalDays'];
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

        // leaveTypeId is a client-writable property (the picker sets it via
        // pickType()) — re-check eligibility server-side rather than trust
        // that a hidden type was never selected, closing the gap a tampered
        // request could otherwise use to submit against a type the visible
        // list itself was built to exclude.
        if (! $this->visibleLeaveTypes()->contains('id', $type->id)) {
            $this->addError('leaveTypeId', 'You are not eligible for that leave type.');

            return;
        }

        try {
            $request = app(LeaveRequestService::class)->apply(
                $me,
                $type,
                \Carbon\Carbon::parse($this->startDate),
                \Carbon\Carbon::parse($this->endDate),
                $this->duration,
                $this->relieverId,
                $this->reason ?: null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $hasSupervisor = (bool) $me->supervisor_id;

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
        $types = $this->visibleLeaveTypes()->map(fn (LeaveType $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'available' => $me->leaveBalance($t)['available'],
        ]);

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
                <legend style="font-size:15px;font-weight:600;margin-bottom:10px;padding:0;">Leave type</legend>
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
                <legend style="font-size:15px;font-weight:600;margin-bottom:10px;padding:0;">Duration per day</legend>
                <div class="seg-switch">
                    @foreach($durationLabels as $key => $label)
                        <button type="button" wire:click="pickDuration('{{ $key }}')" wire:key="dur-{{ $key }}" class="seg-btn {{ $duration === $key ? 'is-active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:var(--fs-sm);color:var(--color-text-muted);">
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
            <span class="text-muted" style="font-size:var(--fs-xs);">Two-stage approval: Line Manager, then HR.</span>
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
                <span class="text-muted" style="font-size:var(--fs-xs);">Jan – Dec {{ now()->year }}</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:8px;">
                <span class="font-mono" style="font-size:30px;font-weight:600;">{{ number_format($afterDays, 1) }}</span>
                <span class="text-muted" style="font-size:var(--fs-sm);">days left after this request</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:var(--fs-sm);margin-top:14px;">
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
