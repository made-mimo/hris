<?php

use App\Models\Employee;
use App\Models\FeedbackCycle;
use App\Models\FeedbackParticipant;
use App\Models\FeedbackTemplate;
use App\Services\PerformanceService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Spec D2's 360° feedback: "only a subject's manager (or HR/Admin) may
 * initiate a cycle... results are visible to HR/Admin and the initiating
 * manager immediately, and to the subject only once explicitly shared."
 */
new class extends Component
{
    public ?int $templateId = null;

    public ?int $subjectId = null;

    public string $dueDate = '';

    public bool $sharedWithSubject = false;

    public array $participantIds = [];

    public array $participantRelationships = [];

    public ?int $respondingTo = null;

    public array $responseInputs = [];

    public ?int $viewingResultsFor = null;

    public function mount(): void
    {
        $this->dueDate = now()->addWeeks(2)->toDateString();
    }

    public function initiate(PerformanceService $performance): void
    {
        $data = $this->validate([
            'templateId' => ['required', 'exists:feedback_templates,id'],
            'subjectId' => ['required', 'exists:employees,id'],
            'dueDate' => ['required', 'date'],
            'participantIds' => ['required', 'array', 'min:1'],
        ]);

        $participants = collect($data['participantIds'])->map(fn ($employeeId) => [
            'employeeId' => $employeeId,
            'relationship' => $this->participantRelationships[$employeeId] ?? 'peer',
        ])->all();

        try {
            $performance->initiateCycle(
                $data['templateId'],
                Employee::findOrFail($data['subjectId']),
                auth()->user(),
                $data['dueDate'],
                $this->sharedWithSubject,
                $participants,
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->reset('templateId', 'subjectId', 'participantIds', 'participantRelationships', 'sharedWithSubject');
        session()->flash('status', '360° feedback cycle initiated.');
    }

    public function startResponse(int $participantId): void
    {
        $participant = FeedbackParticipant::with('cycle.template.questions')->findOrFail($participantId);
        $this->respondingTo = $participantId;
        $this->responseInputs = $participant->cycle->template->questions->mapWithKeys(fn ($q) => [$q->id => ['rating' => null, 'comment' => '']])->all();
    }

    public function submitResponse(PerformanceService $performance): void
    {
        $participant = FeedbackParticipant::findOrFail($this->respondingTo);
        $performance->submitFeedback($participant, $this->responseInputs);
        $this->reset('respondingTo', 'responseInputs');
        session()->flash('status', 'Feedback submitted. Thank you.');
    }

    public function with(PerformanceService $performance): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $isHr = $user->isAdmin() || $user->isHr();

        $myPending = FeedbackParticipant::with('cycle.subject', 'cycle.template')
            ->where('rater_employee_id', $me?->id)
            ->where('status', 'pending')
            ->get();

        $cycles = FeedbackCycle::with(['template', 'subject', 'initiatedBy'])
            ->when(! $isHr, fn ($q) => $q->where(fn ($q2) => $q2->where('initiated_by', $user->id)->orWhere(fn ($q3) => $q3->where('subject_employee_id', $me?->id)->where('shared_with_subject', true))))
            ->latest()
            ->get();

        $initiableSubjects = $isHr ? Employee::orderBy('last_name')->get() : ($me ? $me->subordinates()->orderBy('last_name')->get() : collect());
        $allEmployees = Employee::orderBy('last_name')->get();

        return [
            'myPending' => $myPending,
            'cycles' => $cycles,
            'initiableSubjects' => $initiableSubjects,
            'allEmployees' => $allEmployees,
            'templates' => FeedbackTemplate::where('is_active', true)->get(),
            'canInitiate' => $initiableSubjects->isNotEmpty(),
            'aggregatedResults' => fn ($cycle) => $performance->aggregatedResults($cycle),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-danger/10 px-3.5 py-2.5 text-xs font-semibold text-danger">{{ session('error') }}</div>
    @endif

    @if($myPending->isNotEmpty())
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">Feedback requested from you</h2>
            @foreach($myPending as $participant)
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-text">{{ $participant->cycle->template->name }} for {{ $participant->cycle->subject->fullName() }}</span>
                    <button wire:click="startResponse({{ $participant->id }})" class="text-xs font-semibold text-primary">Respond</button>
                </div>
            @endforeach

            @if($respondingTo)
                @php($participant = \App\Models\FeedbackParticipant::with('cycle.template.questions')->find($respondingTo))
                <div class="mt-3 rounded-sm border border-border bg-bg p-3.5">
                    @foreach($participant->cycle->template->questions as $q)
                        <div class="mb-3">
                            <label class="mb-1 block text-xs font-semibold text-text">{{ $q->question_text }} ({{ $q->min_scale }}–{{ $q->max_scale }})</label>
                            <div class="flex gap-2">
                                <input type="number" step="0.5" min="{{ $q->min_scale }}" max="{{ $q->max_scale }}" wire:model="responseInputs.{{ $q->id }}.rating" class="w-20 rounded-sm border border-border bg-surface px-2 py-1.5 text-xs text-text outline-none focus:border-primary">
                                <input type="text" wire:model="responseInputs.{{ $q->id }}.comment" placeholder="Comment (optional)" class="flex-1 rounded-sm border border-border bg-surface px-2 py-1.5 text-xs text-text outline-none focus:border-primary">
                            </div>
                        </div>
                    @endforeach
                    <button wire:click="submitResponse" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Submit feedback</button>
                    <button wire:click="$set('respondingTo', null)" class="ml-2 text-xs font-semibold text-text-muted">Cancel</button>
                </div>
            @endif
        </section>
    @endif

    @if($canInitiate)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">Initiate 360° feedback</h2>
            <form wire:submit="initiate" class="flex flex-col gap-3.5">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Template</label>
                        <select wire:model="templateId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                            <option value="">— select —</option>
                            @foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                        @error('templateId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Subject</label>
                        <select wire:model="subjectId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                            <option value="">— select —</option>
                            @foreach($initiableSubjects as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                        </select>
                        @error('subjectId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Due date</label>
                        <input type="date" wire:model="dueDate" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Participants</label>
                    <div class="flex flex-col gap-1.5">
                        @foreach($allEmployees as $e)
                            <div class="flex items-center gap-2 text-xs">
                                <input type="checkbox" wire:model="participantIds" value="{{ $e->id }}" class="h-3.5 w-3.5 accent-primary">
                                <span class="w-40 text-text">{{ $e->fullName() }}</span>
                                <select wire:model="participantRelationships.{{ $e->id }}" class="rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                    @foreach(\App\Models\FeedbackParticipant::RELATIONSHIPS as $rel)
                                        <option value="{{ $rel }}">{{ ucfirst(str_replace('_', ' ', $rel)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    @error('participantIds') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>

                <label class="flex items-center gap-1.5 text-xs text-text">
                    <input type="checkbox" wire:model="sharedWithSubject" class="h-4 w-4 accent-primary">
                    Share results with the subject once complete
                </label>

                <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Initiate cycle</button>
            </form>
        </section>
    @endif

    <div class="flex flex-col gap-3">
        @foreach($cycles as $cycle)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="font-display text-sm font-bold text-text">{{ $cycle->template->name }} — {{ $cycle->subject->fullName() }}</div>
                        <div class="text-xs text-text-muted">Due {{ $cycle->due_date->format('j M Y') }} · initiated by {{ $cycle->initiatedBy->name }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text">{{ ucfirst($cycle->status) }}</span>
                        <button wire:click="$set('viewingResultsFor', {{ $viewingResultsFor === $cycle->id ? 'null' : $cycle->id }})" class="text-xs font-semibold text-primary">Results</button>
                    </div>
                </div>

                @if($viewingResultsFor === $cycle->id)
                    @php($results = $aggregatedResults($cycle))
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Individually attributed</div>
                            @forelse($results['attributed'] as $a)
                                <div class="mb-1 text-xs text-text-muted">{{ ucfirst($a['relationship']) }} ({{ $a['rater'] }}): {{ $a['responses']->pluck('rating')->filter()->avg() }} avg</div>
                            @empty
                                <div class="text-xs text-text-muted">None yet.</div>
                            @endforelse
                        </div>
                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Pooled &amp; anonymized (peer / direct-report)</div>
                            @forelse($results['pooled'] as $p)
                                <div class="mb-1 text-xs text-text-muted">{{ $p['question']->question_text }}: avg {{ $p['average_rating'] }} ({{ $p['comments']->count() }} comment(s))</div>
                            @empty
                                <div class="text-xs text-text-muted">None yet.</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($cycles->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No 360° feedback cycles yet.</div>
        @endif
    </div>
</div>
