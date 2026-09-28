<?php

use App\Models\Location;
use App\Models\PulseSurveyTemplate;
use App\Models\SubUnit;
use App\Services\PulseSurveyService;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    public ?int $templateId = null;

    public string $launchDate = '';

    public string $closeDate = '';

    public string $audienceScope = 'all';

    public ?int $subUnitId = null;

    public ?int $locationId = null;

    public bool $isRecurring = false;

    public ?int $recurrenceMonths = 3;

    public function mount(): void
    {
        $this->launchDate = now()->toDateString();
        $this->closeDate = now()->addDays(7)->toDateString();
    }

    public function launch(PulseSurveyService $pulseSurveys): void
    {
        $data = $this->validate([
            'templateId' => ['required', 'exists:pulse_survey_templates,id'],
            'launchDate' => ['required', 'date'],
            'closeDate' => ['required', 'date', 'after:launchDate'],
            'audienceScope' => ['required', 'in:all,department,location'],
            'subUnitId' => ['required_if:audienceScope,department', 'nullable', 'exists:sub_units,id'],
            'locationId' => ['required_if:audienceScope,location', 'nullable', 'exists:locations,id'],
            'isRecurring' => ['boolean'],
            'recurrenceMonths' => ['required_if:isRecurring,true', 'nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $pulseSurveys->launchRun(
            PulseSurveyTemplate::findOrFail($data['templateId']),
            Carbon::parse($data['launchDate']),
            Carbon::parse($data['closeDate']),
            $data['audienceScope'],
            $data['audienceScope'] === 'department' ? SubUnit::findOrFail($data['subUnitId']) : null,
            $data['audienceScope'] === 'location' ? Location::findOrFail($data['locationId']) : null,
            $this->isRecurring,
            $this->isRecurring ? $data['recurrenceMonths'] : null,
        );

        $this->reset('templateId', 'subUnitId', 'locationId', 'isRecurring', 'recurrenceMonths');
        $this->audienceScope = 'all';
        $this->recurrenceMonths = 3;
        $this->launchDate = now()->toDateString();
        $this->closeDate = now()->addDays(7)->toDateString();
        session()->flash('status', 'Survey run launched.');
    }

    public function with(): array
    {
        return [
            'templates' => PulseSurveyTemplate::orderBy('name')->get(),
            'subUnits' => SubUnit::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="launch" class="flex flex-col gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Template</label>
                <select wire:model.live="templateId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">— select —</option>
                    @foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                </select>
                @error('templateId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Launch date</label>
                    <input type="date" wire:model.live="launchDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('launchDate') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Close date</label>
                    <input type="date" wire:model.live="closeDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('closeDate') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Audience</label>
                <select wire:model.live="audienceScope" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="all">All employees</option>
                    <option value="department">A department</option>
                    <option value="location">A location</option>
                </select>
            </div>

            @if($audienceScope === 'department')
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Department</label>
                    <select wire:model.live="subUnitId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($subUnits as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                    @error('subUnitId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            @elseif($audienceScope === 'location')
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Location</label>
                    <select wire:model.live="locationId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                    </select>
                    @error('locationId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            @endif

            <label class="flex items-center gap-1.5 text-xs text-text">
                <input type="checkbox" wire:model.live="isRecurring" class="h-3.5 w-3.5 accent-primary">
                Repeat automatically
            </label>

            @if($isRecurring)
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Every N months</label>
                    <input type="number" wire:model.live="recurrenceMonths" min="1" max="24" class="w-32 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('recurrenceMonths') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            @endif

            <button type="submit" class="self-start rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Launch run</button>
        </form>
    </section>
</div>
