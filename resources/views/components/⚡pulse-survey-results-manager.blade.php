<?php

use App\Models\PulseSurveyRun;
use App\Services\PulseSurveyService;
use Livewire\Component;

new class extends Component
{
    public ?int $expandedId = null;

    public function with(PulseSurveyService $pulseSurveys): array
    {
        $runs = PulseSurveyRun::with('template')->latest('launch_date')->get();

        $results = [];
        if ($this->expandedId) {
            $run = $runs->firstWhere('id', $this->expandedId);
            if ($run) {
                $results = $pulseSurveys->aggregatedResults($run);
            }
        }

        return ['runs' => $runs, 'results' => $results];
    }
};
?>

<div class="flex flex-col gap-3">
    @forelse($runs as $run)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <button type="button" wire:click="$set('expandedId', {{ $expandedId === $run->id ? 'null' : $run->id }})" class="flex w-full items-center justify-between gap-3 text-left">
                <div>
                    <div class="font-display text-sm font-bold text-text">{{ $run->template->name }}</div>
                    <div class="text-xs text-text-muted">{{ $run->audienceLabel() }} · {{ $run->launch_date->format('j M Y') }} – {{ $run->close_date->format('j M Y') }}{{ $run->is_recurring ? ' · recurring every '.$run->recurrence_months.'mo' : '' }}</div>
                </div>
                <span class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $run->status === 'open' ? 'bg-info-light text-info' : ($run->status === 'closed' ? 'bg-text-faint/15 text-text-muted' : 'bg-warning-light text-warning') }}">{{ ucfirst($run->status) }}</span>
            </button>

            @if($expandedId === $run->id)
                <div class="mt-3 border-t border-border pt-3">
                    @if(! ($results['sufficient'] ?? false))
                        <div class="text-sm text-text-muted">Only {{ $results['count'] ?? 0 }} of the required {{ $results['minRequired'] ?? '?' }} responses received — results are withheld until the anonymization threshold is met.</div>
                    @else
                        <div class="mb-4">
                            <div class="text-2xl font-bold text-text">{{ $results['count'] }}</div>
                            <div class="text-xs text-text-muted">Total responses</div>
                        </div>

                        <div class="flex flex-col gap-5">
                            @foreach($results['questions'] as $q)
                                <div class="rounded-md border border-border bg-bg p-4">
                                    <div class="mb-3 text-sm font-semibold text-text">{{ $q['question']->prompt }}</div>

                                    @if($q['question']->type === 'free_text')
                                        @if($q['flaggedCount'] > 0)
                                            <div class="mb-2 text-xs text-warning">{{ $q['flaggedCount'] }} flagged comment(s) hidden.</div>
                                        @endif
                                        @if($q['freeText']->isNotEmpty())
                                            <ul class="flex flex-col gap-1.5">
                                                @foreach($q['freeText'] as $text)
                                                    <li class="rounded-sm bg-surface px-3 py-2 text-xs text-text">{{ $text }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="text-xs text-text-muted">No comments left for this question.</div>
                                        @endif
                                    @else
                                        <div class="mb-3 flex flex-wrap gap-6">
                                            <div>
                                                <div class="text-xl font-bold text-text">{{ $q['average'] ?? '—' }}</div>
                                                <div class="text-xs text-text-muted">Average score</div>
                                            </div>
                                            @if(isset($q['enps']))
                                                <div>
                                                    <div class="text-xl font-bold text-text">{{ $q['enps'] }}</div>
                                                    <div class="text-xs text-text-muted">eNPS</div>
                                                </div>
                                            @endif
                                        </div>

                                        @if($q['count'] > 0)
                                            <div class="grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <div class="mb-1.5 text-xs font-semibold text-text-muted">Response distribution</div>
                                                    <x-chart-canvas
                                                        :id="'pulse-distribution-'.$run->id.'-'.$q['question']->id"
                                                        type="bar"
                                                        :labels="$q['distribution']->keys()->all()"
                                                        :datasets="[['label' => 'Responses', 'data' => $q['distribution']->values()->all(), 'backgroundColor' => '#2563EB']]"
                                                        :height="180"
                                                    />
                                                </div>
                                                @if(isset($q['enps']))
                                                    <div>
                                                        <div class="mb-1.5 text-xs font-semibold text-text-muted">Promoters / passives / detractors</div>
                                                        <x-chart-canvas
                                                            :id="'pulse-enps-'.$run->id.'-'.$q['question']->id"
                                                            type="doughnut"
                                                            :labels="['Promoters', 'Passives', 'Detractors']"
                                                            :datasets="[['data' => [$q['promoters'], $q['passives'], $q['detractors']], 'backgroundColor' => ['#1E9E63', '#B45309', '#DC2626']]]"
                                                            :height="180"
                                                        />
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </section>
    @empty
        <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No survey runs yet.</div>
    @endforelse
</div>
