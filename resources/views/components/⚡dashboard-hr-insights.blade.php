<?php

use App\Models\HelpdeskCategory;
use App\Models\HrMetricsSnapshot;
use App\Models\PulseSurveyRun;
use App\Services\HrMetricsService;
use App\Services\PulseSurveyService;
use App\Services\TicketService;
use Livewire\Component;

/**
 * Spec F2: three "New:" dashboard additions bundled into one Admin/HR Admin
 * section since they share that same restricted audience — latest eNPS tile
 * (F8), open Helpdesk tickets by category excluding confidential categories
 * from non-handlers (F4), and the rolling HR-metrics trend.
 */
new class extends Component
{
    public function with(PulseSurveyService $pulseSurveys, TicketService $tickets, HrMetricsService $hrMetrics): array
    {
        $user = auth()->user();

        // eNPS tile: the two most recent runs with enough responses to report, most recent first.
        $scoredRuns = PulseSurveyRun::with('template')
            ->whereHas('template', fn ($q) => $q->where('scale_type', 'enps_0_10'))
            ->whereIn('status', ['open', 'closed'])
            ->latest('launch_date')
            ->get()
            ->map(fn (PulseSurveyRun $run) => ['run' => $run, 'results' => $pulseSurveys->aggregatedResults($run)])
            ->filter(fn ($row) => $row['results']['sufficient'] ?? false)
            ->take(2)
            ->values();

        $latestEnps = $scoredRuns->first();
        $priorEnps = $scoredRuns->get(1);
        $enpsTrend = $latestEnps && $priorEnps
            ? $latestEnps['results']['enps'] <=> $priorEnps['results']['enps']
            : null;

        // Helpdesk summary: open-ticket counts by category, excluding confidential categories the viewer doesn't handle.
        $helpdeskCategories = HelpdeskCategory::where('is_active', true)
            ->withCount(['tickets' => fn ($q) => $q->whereIn('status', ['open', 'in_progress'])])
            ->get()
            ->filter(fn (HelpdeskCategory $c) => ! $c->is_confidential || $user->isAdmin() || $tickets->isHandler($c, $user))
            ->filter(fn (HelpdeskCategory $c) => $c->tickets_count > 0)
            ->sortByDesc('tickets_count')
            ->values();

        return [
            'latestEnps' => $latestEnps,
            'enpsTrend' => $enpsTrend,
            'helpdeskCategories' => $helpdeskCategories,
            'metricsTrend' => $hrMetrics->monthlyTrend(),
            'latestSnapshot' => $hrMetrics->latest(),
        ];
    }
};
?>

<div class="grid grid-3" style="gap:18px;">
    <section class="card">
        <div class="card-header">
            <h2>Employee eNPS</h2>
        </div>
        @if($latestEnps)
            <div style="display:flex;align-items:baseline;gap:8px;">
                <div class="stat-value">{{ $latestEnps['results']['enps'] }}</div>
                @if($enpsTrend !== null)
                    <span class="pill {{ $enpsTrend > 0 ? 'pill-success' : ($enpsTrend < 0 ? 'pill-danger' : 'pill-neutral') }}">
                        {{ $enpsTrend > 0 ? '▲' : ($enpsTrend < 0 ? '▼' : '—') }}
                    </span>
                @endif
            </div>
            <div class="text-muted" style="font-size:12px;margin-top:4px;">{{ $latestEnps['run']->template->name }} · {{ $latestEnps['results']['count'] }} responses</div>
        @else
            <p class="text-muted">No pulse survey run has enough responses yet to report an eNPS score.</p>
        @endif
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Open helpdesk tickets</h2>
        </div>
        @forelse($helpdeskCategories as $c)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--color-border);">
                <span style="font-size:13px;">{{ $c->name }}</span>
                <span class="font-mono" style="font-weight:600;">{{ $c->tickets_count }}</span>
            </div>
        @empty
            <p class="text-muted">No open tickets.</p>
        @endforelse
    </section>

    <section class="card">
        <div class="card-header">
            <h2>HR metrics trend</h2>
        </div>
        @if($latestSnapshot)
            <div class="text-muted" style="font-size:12px;margin-bottom:8px;">
                {{ $latestSnapshot->active_headcount }} active · {{ $latestSnapshot->turnover_rate_percent }}% turnover (30d) · {{ $latestSnapshot->open_requisitions }} open roles
            </div>
            <div style="display:flex;align-items:flex-end;gap:4px;height:60px;">
                @php $maxHeadcount = $metricsTrend->max('active_headcount') ?: 1; @endphp
                @foreach($metricsTrend as $point)
                    <div title="{{ $point->snapshot_date->format('F Y') }}: {{ $point->active_headcount }}" style="flex:1;background:var(--color-primary);border-radius:3px 3px 0 0;height:{{ max(4, round($point->active_headcount / $maxHeadcount * 60)) }}px;"></div>
                @endforeach
            </div>
            <div class="text-muted" style="font-size:11px;margin-top:4px;">Active headcount, last {{ $metricsTrend->count() }} month{{ $metricsTrend->count() === 1 ? '' : 's' }}</div>
        @else
            <p class="text-muted">No snapshot yet — the nightly job hasn't run.</p>
        @endif
    </section>
</div>
