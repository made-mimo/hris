<?php

use App\Models\PulseSurveyTemplate;
use App\Models\Setting;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $primaryQuestion = '';

    public string $scaleType = 'enps_0_10';

    public string $freeTextQuestion = '';

    public int $minResponses;

    public function mount(): void
    {
        $this->minResponses = Setting::current()->pulse_survey_min_responses;
    }

    public function create(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'primaryQuestion' => ['required', 'string', 'max:300'],
            'scaleType' => ['required', 'in:likert_5,enps_0_10'],
            'freeTextQuestion' => ['nullable', 'string', 'max:300'],
        ]);

        PulseSurveyTemplate::create([
            'name' => $data['name'],
            'primary_question' => $data['primaryQuestion'],
            'scale_type' => $data['scaleType'],
            'free_text_question' => $data['freeTextQuestion'] ?: null,
        ]);

        $this->reset('name', 'primaryQuestion', 'freeTextQuestion');
        $this->scaleType = 'enps_0_10';
        session()->flash('status', 'Template created.');
    }

    public function saveMinResponses(): void
    {
        $data = $this->validate(['minResponses' => ['required', 'integer', 'min:1', 'max:100']]);

        Setting::current()->update(['pulse_survey_min_responses' => $data['minResponses']]);
        session()->flash('status', 'Anonymization threshold updated.');
    }

    public function with(): array
    {
        return ['templates' => PulseSurveyTemplate::latest()->get()];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3 font-display text-sm font-bold text-text">Anonymization threshold</div>
        <form wire:submit="saveMinResponses" class="flex items-end gap-3">
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
        <form wire:submit="create" class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-3">
                <div style="flex:1;min-width:200px;">
                    <label class="mb-1.5 block text-xs font-semibold text-text">Name</label>
                    <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Scale</label>
                    <select wire:model="scaleType" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="enps_0_10">eNPS (0-10)</option>
                        <option value="likert_5">Likert (1-5)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Primary question</label>
                <input type="text" wire:model="primaryQuestion" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('primaryQuestion') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Free-text follow-up question <span class="text-text-muted">(optional)</span></label>
                <input type="text" wire:model="freeTextQuestion" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <button type="submit" class="self-start rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Create template</button>
        </form>
    </section>

    <div class="flex flex-col gap-2">
        @foreach($templates as $t)
            <section class="rounded-md border border-border bg-surface p-4 shadow-sm">
                <div class="font-display text-sm font-bold text-text">{{ $t->name }}</div>
                <div class="text-xs text-text-muted">{{ $t->primary_question }} · {{ $t->scale_type === 'enps_0_10' ? 'eNPS (0-10)' : 'Likert (1-5)' }}</div>
            </section>
        @endforeach
        @if($templates->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No templates yet.</div>
        @endif
    </div>
</div>
