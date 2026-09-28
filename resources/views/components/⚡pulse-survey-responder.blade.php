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
    public array $scaleValue = [];

    public array $freeText = [];

    public function respond(int $runId, PulseSurveyService $pulseSurveys): void
    {
        $run = PulseSurveyRun::findOrFail($runId);
        $employee = auth()->user()->employee;

        $scale = $this->scaleValue[$runId] ?? null;
        $max = $run->template->scale_type === 'enps_0_10' ? 10 : 5;

        $this->validate([
            'scaleValue.'.$runId => ['required', 'integer', 'min:0', 'max:'.$max],
        ]);

        $pulseSurveys->respond($run, $employee, (int) $scale, trim($this->freeText[$runId] ?? '') ?: null);

        unset($this->scaleValue[$runId], $this->freeText[$runId]);
        session()->flash('status', 'Thanks — your response has been recorded anonymously.');
    }

    public function with(PulseSurveyService $pulseSurveys): array
    {
        $employee = auth()->user()->employee;

        $openRuns = PulseSurveyRun::with('template')
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

            <div class="mb-3 text-sm text-text">{{ $run->template->primary_question }}</div>

            <div class="mb-3 flex flex-wrap gap-2">
                @php $max = $run->template->scale_type === 'enps_0_10' ? 10 : 5; @endphp
                @for($i = $run->template->scale_type === 'enps_0_10' ? 0 : 1; $i <= $max; $i++)
                    <button type="button" wire:click="$set('scaleValue.{{ $run->id }}', {{ $i }})"
                        class="h-9 w-9 rounded-sm border text-sm font-semibold {{ ($scaleValue[$run->id] ?? null) === $i ? 'border-primary bg-primary text-white' : 'border-border bg-surface text-text hover:border-primary' }}">
                        {{ $i }}
                    </button>
                @endfor
            </div>
            @error('scaleValue.'.$run->id) <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

            @if($run->template->free_text_question)
                <div class="mb-3">
                    <label class="mb-1.5 block text-xs font-semibold text-text">{{ $run->template->free_text_question }} <span class="text-text-muted">(optional)</span></label>
                    <textarea wire:model="freeText.{{ $run->id }}" rows="2" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                </div>
            @endif

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
