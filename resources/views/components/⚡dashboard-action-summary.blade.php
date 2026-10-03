<?php

use App\Models\Interview;
use App\Models\LeaveRequest;
use App\Models\ModuleToggle;
use App\Models\PerformanceReviewer;
use App\Models\Timesheet;
use App\Services\WorkflowEngine;
use Livewire\Component;

/**
 * Spec F2: "a personal/team 'action summary' (pending approvals across
 * leave, timesheets, reviews, interviews — each included only if its source
 * module is enabled)." Leave/timesheet counts reuse WorkflowEngine::pendingFor()
 * — the same authoritative, reporting-line-scoped source the Approvals board
 * itself queries — rather than a naive org-wide status count.
 */
new class extends Component
{
    protected WorkflowEngine $engine;

    public function boot(WorkflowEngine $engine): void
    {
        $this->engine = $engine;
    }

    public function with(): array
    {
        $user = auth()->user();
        $me = $user->employee;

        $items = [];

        if (ModuleToggle::isEnabled('leave')) {
            $count = $this->engine->pendingFor($user, 'leave_request', LeaveRequest::class, ['pending_manager', 'pending_hr'])->count();
            if ($count > 0) {
                $items[] = ['label' => 'Leave request'.($count === 1 ? '' : 's').' to review', 'count' => $count, 'route' => 'approvals'];
            }
        }

        if (ModuleToggle::isEnabled('timesheets')) {
            $count = $this->engine->pendingFor($user, 'timesheet', Timesheet::class, ['submitted'])->count();
            if ($count > 0) {
                $items[] = ['label' => 'Timesheet'.($count === 1 ? '' : 's').' to approve', 'count' => $count, 'route' => 'timesheets.approvals'];
            }
        }

        if (ModuleToggle::isEnabled('performance') && $me) {
            $count = PerformanceReviewer::where('employee_id', $me->id)->where('status', 'pending')->count();
            if ($count > 0) {
                $items[] = ['label' => 'Performance review'.($count === 1 ? '' : 's').' awaiting you', 'count' => $count, 'route' => 'performance'];
            }
        }

        if (ModuleToggle::isEnabled('recruitment') && $me) {
            $count = Interview::whereHas('interviewers', fn ($q) => $q->where('employees.id', $me->id))
                ->where('interview_date', '>=', now()->startOfDay())
                ->count();
            if ($count > 0) {
                $items[] = ['label' => 'Upcoming interview'.($count === 1 ? '' : 's').' to conduct', 'count' => $count, 'route' => 'recruitment'];
            }
        }

        return ['items' => $items];
    }
};
?>

<section class="card">
    <div class="card-header">
        <h2>Action summary</h2>
    </div>
    @if(count($items) > 0)
        <div style="display:flex;flex-direction:column;gap:10px;">
            @foreach($items as $item)
                <a href="{{ route($item['route']) }}" wire:navigate style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--color-bg);border-radius:10px;text-decoration:none;color:inherit;">
                    <span style="font-size:var(--fs-base);">{{ $item['label'] }}</span>
                    <span class="pill pill-danger">{{ $item['count'] }}</span>
                </a>
            @endforeach
        </div>
    @else
        <p class="text-muted">Nothing needs your attention right now.</p>
    @endif
</section>
