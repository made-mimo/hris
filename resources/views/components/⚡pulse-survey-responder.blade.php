<?php

use App\Models\PulseSurveyRun;
use App\Services\PulseSurveyService;
use Livewire\Component;

/**
 * Spec F8: "any employee in the targeted audience may respond once per run;
 * responses are anonymous — never attributable to the responder in any UI."
 */
new class extends Component
{
    /** @var array<int, array<int, int>> runId => [questionId => scaleValue] */
    public array $scaleValue = [];

    /** @var array<int, array<int, string>> runId => [questionId => freeText] */
    public array $freeText = [];

    public function respond(int $runId, PulseSurveyService $pulseSurveys): void
    {
        $run = PulseSurveyRun::with('questions')->findOrFail($runId);
        $employee = auth()->user()->employee;

        $rules = [];
        foreach ($run->questions as $question) {
            if ($question->type === 'scale') {
                $rules['scaleValue.'.$runId.'.'.$question->id] = ['required', 'integer', 'min:'.$question->scaleMin(), 'max:'.$question->scaleMax()];
            }
        }
        $this->validate($rules);

        $answers = [];
        foreach ($run->questions as $question) {
            $answers[$question->id] = $question->type === 'scale'
                ? ['scale_value' => (int) ($this->scaleValue[$runId][$question->id] ?? null)]
                : ['free_text' => trim($this->freeText[$runId][$question->id] ?? '') ?: null];
        }

        $pulseSurveys->respond($run, $employee, $answers);

        unset($this->scaleValue[$runId], $this->freeText[$runId]);
        session()->flash('status', 'Thanks — your response has been recorded anonymously.');
    }

    public function with(PulseSurveyService $pulseSurveys): array
    {
        $employee = auth()->user()->employee;

        $openRuns = PulseSurveyRun::with(['template', 'questions'])
            ->where('status', 'open')
            ->get()
            ->filter(fn (PulseSurveyRun $run) => $pulseSurveys->audienceEmployees($run)->contains('id', $employee?->id)
                && ! $pulseSurveys->hasResponded($run, $employee));

        $respondedRuns = PulseSurveyRun::with('template')
            ->where('status', 'open')
            ->get()
            ->filter(fn (PulseSurveyRun $run) => $pulseSurveys->hasResponded($run, $employee));

        return [
            'openRuns' => $openRuns->values(),
            'respondedRuns' => $respondedRuns->values(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    @forelse($openRuns as $run)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <div class="font-display text-sm font-bold text-text">{{ $run->template->name }}</div>
            <div class="mb-3 text-xs text-text-muted">Closes {{ $run->close_date->format('j M Y') }} · {{ $run->audienceLabel() }}</div>

            @foreach($run->questions as $question)
                <div class="mb-4">
                    <div class="mb-2 text-sm text-text">{{ $question->prompt }} @if($question->type === 'free_text')<span class="text-text-muted">(optional)</span>@endif</div>

                    @if($question->type === 'scale')
                        <div class="flex flex-wrap gap-2">
                            @for($i = $question->scaleMin(); $i <= $question->scaleMax(); $i++)
                                <button type="button" wire:click="$set('scaleValue.{{ $run->id }}.{{ $question->id }}', {{ $i }})"
                                    class="h-9 w-9 rounded-sm border text-sm font-semibold {{ ($scaleValue[$run->id][$question->id] ?? null) === $i ? 'border-primary bg-primary text-white' : 'border-border bg-surface text-text hover:border-primary' }}">
                                    {{ $i }}
                                </button>
                            @endfor
                        </div>
                        @error('scaleValue.'.$run->id.'.'.$question->id) <div class="mt-1.5 text-xs text-danger">{{ $message }}</div> @enderror
                    @else
                        <textarea wire:model="freeText.{{ $run->id }}.{{ $question->id }}" rows="2" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                    @endif
                </div>
            @endforeach

            <button wire:click="respond({{ $run->id }})" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Submit anonymously</button>
        </section>
    @empty
        <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No pulse surveys open for you right now.</div>
    @endforelse

    @if($respondedRuns->isNotEmpty())
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <div class="mb-2 font-display text-sm font-bold text-text">Already responded</div>
            <ul class="flex flex-col gap-1">
                @foreach($respondedRuns as $run)
                    <li class="text-xs text-text-muted">{{ $run->template->name }} — thanks for your feedback, {{ $run->close_date->format('j M Y') }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
