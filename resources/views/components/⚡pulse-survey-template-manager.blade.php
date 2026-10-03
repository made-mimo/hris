<?php

use App\Models\PulseSurveyQuestion;
use App\Models\PulseSurveyTemplate;
use App\Models\Setting;
use Livewire\Component;

/** Backlog #11 — a template is now a question bank: create it, then add scale/free-text questions to it one at a time. A run later picks a subset (pulse-survey-run-manager). */
new class extends Component
{
    public string $name = '';

    public ?int $expandedTemplateId = null;

    public string $questionType = 'scale';

    public string $questionPrompt = '';

    public string $questionScaleType = 'enps_0_10';

    public int $minResponses;

    public function mount(): void
    {
        $this->minResponses = Setting::current()->pulse_survey_min_responses;
    }

    public function create(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:150']]);

        $template = PulseSurveyTemplate::create(['name' => $data['name']]);

        $this->reset('name');
        $this->expandedTemplateId = $template->id;
        session()->flash('status', 'Template created — add its questions below.');
    }

    public function deleteTemplate(int $id): void
    {
        $template = PulseSurveyTemplate::findOrFail($id);

        // Same reasoning as deleteQuestion: a template already used to
        // launch a run has recorded (anonymous) answers hanging off it via
        // that run — deleting it would cascade-delete that run's history.
        abort_if($template->runs()->exists(), 422, 'This template has already been used to launch a survey run and can no longer be deleted.');

        $template->delete();
        session()->flash('status', 'Template deleted.');
    }

    public function addQuestion(int $templateId): void
    {
        $template = PulseSurveyTemplate::findOrFail($templateId);

        $data = $this->validate([
            'questionType' => ['required', 'in:'.implode(',', PulseSurveyQuestion::TYPES)],
            'questionPrompt' => ['required', 'string', 'max:300'],
            'questionScaleType' => ['required_if:questionType,scale', 'nullable', 'in:'.implode(',', PulseSurveyQuestion::SCALE_TYPES)],
        ]);

        $template->questions()->create([
            'type' => $data['questionType'],
            'prompt' => $data['questionPrompt'],
            'scale_type' => $data['questionType'] === 'scale' ? $data['questionScaleType'] : null,
            'sort_order' => (int) $template->questions()->max('sort_order') + 1,
        ]);

        $this->reset('questionPrompt');
        $this->questionType = 'scale';
        $this->questionScaleType = 'enps_0_10';
        session()->flash('status', 'Question added.');
    }

    public function deleteQuestion(int $id): void
    {
        $question = PulseSurveyQuestion::findOrFail($id);

        // A question already asked by a run has recorded (anonymous)
        // answers hanging off it — deleting it would cascade-delete those
        // answers, silently shrinking that run's historical results.
        abort_if($question->runs()->exists(), 422, 'This question has already been used in a survey run and can no longer be removed.');

        $question->delete();
        session()->flash('status', 'Question removed.');
    }

    public function saveMinResponses(): void
    {
        $data = $this->validate(['minResponses' => ['required', 'integer', 'min:1', 'max:100']]);

        Setting::current()->update(['pulse_survey_min_responses' => $data['minResponses']]);
        session()->flash('status', 'Anonymization threshold updated.');
    }

    public function with(): array
    {
        return ['templates' => PulseSurveyTemplate::with('questions')->latest()->get()];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3 font-display text-sm font-bold text-text">Anonymization threshold</div>
        <form wire:submit="saveMinResponses" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Minimum responses before results are shown</label>
                <input type="number" wire:model="minResponses" min="1" max="100" class="w-32 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('minResponses') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save</button>
        </form>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3 font-display text-sm font-bold text-text">New template</div>
        <form wire:submit="create" class="flex flex-wrap items-end gap-3">
            <div style="flex:1;min-width:200px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Name</label>
                <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Create template</button>
        </form>
        <p class="mt-2 text-xs text-text-muted">A run can ask at most {{ \App\Models\PulseSurveyTemplate::MAX_QUESTIONS_PER_RUN }} of a template's questions — kept short by design so completion rates stay high.</p>
    </section>

    <div class="flex flex-col gap-2">
        @foreach($templates as $t)
            <section class="rounded-md border border-border bg-surface p-4 shadow-sm">
                <button type="button" wire:click="$set('expandedTemplateId', {{ $expandedTemplateId === $t->id ? 'null' : $t->id }})" class="flex w-full items-center justify-between gap-3 text-left">
                    <div>
                        <div class="font-display text-sm font-bold text-text">{{ $t->name }}</div>
                        <div class="text-xs text-text-muted">{{ $t->questions->count() }} {{ Str::plural('question', $t->questions->count()) }}</div>
                    </div>
                </button>

                @if($expandedTemplateId === $t->id)
                    <div class="mt-3 border-t border-border pt-3">
                        <div class="mb-3 flex flex-col gap-1.5">
                            @foreach($t->questions as $q)
                                <div class="flex items-center justify-between rounded-sm bg-bg px-3 py-2 text-sm">
                                    <span class="text-text">{{ $q->prompt }} <span class="text-xs text-text-muted">({{ $q->type === 'scale' ? ($q->scale_type === 'enps_0_10' ? 'eNPS 0-10' : 'Likert 1-5') : 'Free text' }})</span></span>
                                    <button wire:click="deleteQuestion({{ $q->id }})" wire:confirm="Remove this question?" class="text-xs font-semibold text-danger">Remove</button>
                                </div>
                            @endforeach
                            @if($t->questions->isEmpty())
                                <div class="text-sm text-text-muted">No questions yet — add one below.</div>
                            @endif
                        </div>

                        <form wire:submit="addQuestion({{ $t->id }})" class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-text">Type</label>
                                <select wire:model.live="questionType" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                    <option value="scale">Scale</option>
                                    <option value="free_text">Free text</option>
                                </select>
                            </div>
                            @if($questionType === 'scale')
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-text">Scale</label>
                                    <select wire:model="questionScaleType" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                        <option value="enps_0_10">eNPS (0-10)</option>
                                        <option value="likert_5">Likert (1-5)</option>
                                    </select>
                                </div>
                            @endif
                            <div style="flex:1;min-width:220px;">
                                <label class="mb-1.5 block text-xs font-semibold text-text">Question</label>
                                <input type="text" wire:model="questionPrompt" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                @error('questionPrompt') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add question</button>
                        </form>

                        <button wire:click="deleteTemplate({{ $t->id }})" wire:confirm="Delete this whole template and its questions?" class="mt-3 text-xs font-semibold text-danger">Delete template</button>
                    </div>
                @endif
            </section>
        @endforeach
        @if($templates->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No templates yet.</div>
        @endif
    </div>
</div>
