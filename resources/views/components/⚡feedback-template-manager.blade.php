<?php

use App\Models\FeedbackTemplate;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public ?int $addingQuestionTo = null;

    public string $questionText = '';

    public float $minScale = 1;

    public float $maxScale = 5;

    public function create(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:150']]);

        FeedbackTemplate::create(['name' => $data['name'], 'is_active' => true]);

        $this->reset('name');
        session()->flash('status', 'Template created.');
    }

    public function addQuestion(): void
    {
        $data = $this->validate([
            'questionText' => ['required', 'string', 'max:255'],
            'minScale' => ['required', 'numeric', 'lt:maxScale'],
            'maxScale' => ['required', 'numeric', 'gt:minScale'],
        ]);

        $template = FeedbackTemplate::findOrFail($this->addingQuestionTo);
        $template->questions()->create([
            'question_text' => $data['questionText'],
            'min_scale' => $data['minScale'],
            'max_scale' => $data['maxScale'],
            'sort_order' => $template->questions()->count(),
        ]);

        $this->reset('questionText', 'addingQuestionTo');
        $this->minScale = 1;
        $this->maxScale = 5;
        session()->flash('status', 'Question added.');
    }

    public function removeQuestion(int $id): void
    {
        \App\Models\FeedbackTemplateQuestion::findOrFail($id)->delete();
    }

    public function with(): array
    {
        return ['templates' => FeedbackTemplate::with('questions')->latest()->get()];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New template</h2>
        <form wire:submit="create" class="flex items-end gap-3">
            <div style="flex:1;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Template name</label>
                <input type="text" wire:model="name" placeholder="e.g. Standard 360" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Create</button>
        </form>
    </section>

    <div class="flex flex-col gap-3">
        @foreach($templates as $template)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <h3 class="mb-2 font-display text-sm font-bold text-text">{{ $template->name }}</h3>
                <div class="flex flex-col gap-1.5">
                    @foreach($template->questions as $q)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-text">{{ $q->sort_order + 1 }}. {{ $q->question_text }} <span class="text-xs text-text-muted">({{ $q->min_scale }}–{{ $q->max_scale }})</span></span>
                            <button wire:click="removeQuestion({{ $q->id }})" class="text-xs font-semibold text-danger">Remove</button>
                        </div>
                    @endforeach
                </div>

                @if($addingQuestionTo === $template->id)
                    <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-border pt-3">
                        <input type="text" wire:model="questionText" placeholder="Question text" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                        <input type="number" step="0.5" wire:model="minScale" class="w-16 rounded-sm border border-border bg-surface px-2 py-1.5 text-xs text-text outline-none focus:border-primary">
                        <input type="number" step="0.5" wire:model="maxScale" class="w-16 rounded-sm border border-border bg-surface px-2 py-1.5 text-xs text-text outline-none focus:border-primary">
                        <button wire:click="addQuestion" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
                        <button wire:click="$set('addingQuestionTo', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                        @error('questionText') <div class="w-full text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                @else
                    <button wire:click="$set('addingQuestionTo', {{ $template->id }})" class="mt-2 text-xs font-semibold text-primary">+ Add question</button>
                @endif
            </section>
        @endforeach
        @if($templates->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No feedback templates yet.</div>
        @endif
    </div>
</div>
