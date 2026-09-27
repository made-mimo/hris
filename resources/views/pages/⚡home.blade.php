<?php

use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        $me = auth()->user()->employee;
        // The greeting/date line is the one place "now" means "now for the
        // viewer" — converted to their browser-declared timezone (never
        // IP/GPS, see PLAN.md), not the server's fixed GMT+1 default.
        $viewerNow = now()->clone()->setTimezone(auth()->user()->displayTimezone());
        $annual = LeaveType::where('slug', 'annual')->first();
        $sick = LeaveType::where('slug', 'sick')->first();

        $openLeave = $me->leaveRequests()->whereIn('status', ['pending_manager', 'pending_hr'])->count();
        $openClaims = $me->expenseClaims()->whereIn('status', ['pending_manager', 'pending_hr'])->count();
        $awaitingPayment = (float) $me->expenseClaims()->where('status', 'approved')
            ->get()->sum(fn ($c) => $c->total());

        $today = now()->startOfDay();
        $outToday = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('employee_id', '!=', $me->id)
            ->when($me->supervisor_id || $me->subordinates()->exists(), fn ($q) => $q->whereIn('employee_id', $me->subordinates()->pluck('id')->push($me->supervisor_id ?? 0)))
            ->get();

        $team = null;
        if ($me->subordinates()->exists()) {
            $subIds = $me->subordinates()->pluck('id');
            $team = [
                'count' => $subIds->count(),
                'leaveToReview' => LeaveRequest::whereIn('employee_id', $subIds)->where('status', 'pending_manager')->count(),
                'claimsToReview' => ExpenseClaim::whereIn('employee_id', $subIds)->where('status', 'pending_manager')->count(),
                'onLeaveThisWeek' => LeaveRequest::whereIn('employee_id', $subIds)->where('status', 'approved')
                    ->whereDate('start_date', '<=', now()->endOfWeek())->whereDate('end_date', '>=', now()->startOfWeek())->count(),
            ];
        }

        return [
            'me' => $me,
            'viewerNow' => $viewerNow,
            'annualBalance' => $annual ? $me->leaveBalance($annual) : null,
            'sickBalance' => $sick ? $me->leaveBalance($sick) : null,
            'openRequests' => $openLeave + $openClaims,
            'openLeave' => $openLeave,
            'openClaims' => $openClaims,
            'awaitingPayment' => $awaitingPayment,
            'outToday' => $outToday,
            'team' => $team,
            'currentPunch' => $me->currentPunch(),
        ];
    }
};
?>

<x-layouts.app title="Home">
    <div class="page-header">
        <div>
            <h1>{{ $viewerNow->hour < 12 ? 'Good morning' : ($viewerNow->hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ $me->first_name }}</h1>
            <p class="text-muted">{{ $viewerNow->format('l, j F Y') }} · {{ $me->jobTitleName() }}{{ $me->departmentName() ? ', '.$me->departmentName() : '' }}</p>
        </div>
        <livewire:clock-toggle />
    </div>

    <div class="grid grid-4" style="margin-top:14px;">
        <div class="stat-card">
            <div class="stat-badge is-primary"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg></div>
            @if($annualBalance)
                <div class="stat-card-flag is-primary">of {{ rtrim(rtrim(number_format($annualBalance['entitled'],1),'0'),'.') }} days</div>
            @endif
            <div class="stat-label">Annual leave available</div>
            <div class="stat-value">{{ $annualBalance ? number_format($annualBalance['available'],1) : '0.0' }} <span class="text-muted" style="font-size:14px;">days</span></div>
            <div class="text-muted" style="font-size:12px;margin-top:6px;">{{ $annualBalance ? number_format($annualBalance['used'],1) : '0.0' }} taken/scheduled</div>
        </div>
        <div class="stat-card">
            <div class="stat-badge is-info"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.5-7 10-7 10z"></path></svg></div>
            <div class="stat-label">Sick leave available</div>
            <div class="stat-value">{{ $sickBalance ? number_format($sickBalance['available'],1) : '0.0' }} <span class="text-muted" style="font-size:14px;">days</span></div>
            <div class="text-muted" style="font-size:12px;margin-top:6px;">{{ $sickBalance && $sickBalance['used'] > 0 ? number_format($sickBalance['used'],1).' taken this year' : 'None taken this year' }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-badge is-warning"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></div>
            @if($openRequests > 0)<div class="stat-card-flag is-warning">In review</div>@endif
            <div class="stat-label">My open requests</div>
            <div class="stat-value">{{ $openRequests }}</div>
            <div class="text-muted" style="font-size:12px;margin-top:6px;">{{ $openLeave }} leave · {{ $openClaims }} claim{{ $openClaims === 1 ? '' : 's' }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-badge is-accent"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"></path><path d="M9 8h6M9 12h6"></path></svg></div>
            @if($awaitingPayment > 0)<div class="stat-card-flag is-accent">Approved</div>@endif
            <div class="stat-label">Claims awaiting payment</div>
            <div class="stat-value">₦{{ number_format($awaitingPayment) }}</div>
            <div class="text-muted" style="font-size:12px;margin-top:6px;">With Finance for payment</div>
        </div>
    </div>

    <div class="grid grid-3" style="margin-top:18px;">
        <livewire:my-recent-requests-table />

        <section class="card-dark">
            <h2>Quick actions</h2>
            <a href="{{ route('leave.apply') }}" wire:navigate class="quick-action-dark">
                <span style="width:34px;height:34px;border-radius:8px;background:var(--color-primary);display:flex;align-items:center;justify-content:center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg></span>
                <span style="flex:1;">Apply for leave</span>
            </a>
            <a href="{{ route('claims.create') }}" wire:navigate class="quick-action-dark">
                <span style="width:34px;height:34px;border-radius:8px;background:var(--color-accent);display:flex;align-items:center;justify-content:center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"></path><path d="M9 8h6M9 12h6"></path></svg></span>
                <span style="flex:1;">Submit a claim</span>
            </a>
            <span class="quick-action-dark" style="opacity:.6;" title="Use the punch button at the top of the page">
                <span style="width:34px;height:34px;border-radius:8px;background:var(--color-info);display:flex;align-items:center;justify-content:center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span>
                <span style="flex:1;">{{ $currentPunch ? 'Punched in '.$currentPunch->punch_in_at_local->format('H:i') : 'Punch in above' }}</span>
            </span>
            <span class="quick-action-dark" style="opacity:.6;">
                <span style="width:34px;height:34px;border-radius:8px;background:var(--color-warning);display:flex;align-items:center;justify-content:center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"></path></svg></span>
                <span style="flex:1;">Raise a helpdesk ticket</span>
            </span>
        </section>
    </div>

    <div class="grid grid-3" style="margin-top:18px;">
        <section class="card">
            <div class="card-header">
                <h2>Who's out today</h2>
                <span class="pill pill-neutral">{{ $outToday->count() }} people</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                @forelse($outToday as $row)
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="avatar" style="width:36px;height:36px;font-size:12px;background:var(--color-primary-light);color:var(--color-primary-dark);">{{ $row->employee->initials }}</div>
                        <div style="flex:1;">
                            <div style="font-size:14px;font-weight:600;">{{ $row->employee->fullName() }}</div>
                            <div class="text-muted" style="font-size:12px;">{{ $row->leaveType->name }} leave · back {{ $row->end_date->addDay()->format('j M') }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">Nobody on your team is out today.</p>
                @endforelse
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2>Tasks for you</h2>
                <span class="pill pill-danger">2 due</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid var(--color-border);border-radius:10px;cursor:pointer;">
                    <input type="checkbox" style="width:16px;height:16px;margin-top:2px;accent-color:var(--color-primary);">
                    <span><span style="display:block;font-size:14px;font-weight:600;">Acknowledge: Data Protection Policy v3</span><span style="display:block;font-size:12px;color:var(--color-warning);margin-top:2px;">Due in 3 days</span></span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid var(--color-border);border-radius:10px;cursor:pointer;">
                    <input type="checkbox" style="width:16px;height:16px;margin-top:2px;accent-color:var(--color-primary);">
                    <span><span style="display:block;font-size:14px;font-weight:600;">Q3 pulse survey</span><span style="display:block;font-size:12px;color:var(--color-text-muted);margin-top:2px;">5 questions · closes in 5 days</span></span>
                </label>
                <div class="hint">Policy Documents (E4) and Pulse Surveys (F8) aren't built in this prototype slice — see PLAN.md §4.</div>
            </div>
        </section>

        <section class="card" style="display:flex;flex-direction:column;">
            <div class="card-header">
                <h2>My team</h2>
                @if($team)<span class="text-muted" style="font-size:12px;">{{ $team['count'] }} direct reports</span>@endif
            </div>
            @if($team)
                <div style="display:flex;flex-direction:column;gap:10px;flex:1;">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--color-bg);border-radius:10px;">
                        <span>Leave requests to review</span><span class="font-mono" style="font-weight:600;">{{ $team['leaveToReview'] }}</span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--color-bg);border-radius:10px;">
                        <span>Claims to review</span><span class="font-mono" style="font-weight:600;">{{ $team['claimsToReview'] }}</span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--color-bg);border-radius:10px;">
                        <span>On leave this week</span><span class="font-mono" style="font-weight:600;">{{ $team['onLeaveThisWeek'] }}</span>
                    </div>
                </div>
                <a href="{{ route('approvals') }}" wire:navigate class="btn btn-outline" style="justify-content:center;margin-top:14px;">Open approvals
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
                </a>
            @else
                <p class="text-muted">You have no direct reports.</p>
            @endif
        </section>
    </div>
</x-layouts.app>
