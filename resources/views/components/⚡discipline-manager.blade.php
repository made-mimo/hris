<?php

use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Services\DisciplinaryCaseService;
use App\Services\PermissionService;
use App\Services\SignatureService;
use App\Services\WorkflowEngine;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Spec D3: strict ownership scoping, HR-only progression past the initial
 * response, structured outcome taxonomy, e-signature-backed acknowledgement
 * for Written Warning and above. Per-record eligibility is computed
 * server-side here and simply hidden in the markup when false, per spec's
 * own "should be computed server-side and surfaced to the client" rule.
 */
new class extends Component
{
    use WithFileUploads;

    public bool $creating = false;

    public ?int $employeeId = null;

    public string $caseType = '';

    public string $severity = 'low';

    public string $description = '';

    public string $incidentDate = '';

    public ?int $expandedId = null;

    public ?int $respondingId = null;

    public string $responseBody = '';

    public ?int $resolvingId = null;

    public string $outcome = '';

    public string $resolutionNote = '';

    public ?int $followingUpId = null;

    public string $followUpBody = '';

    public $file = null;

    public function mount(): void
    {
        $this->incidentDate = now()->toDateString();
    }

    public function raise(DisciplinaryCaseService $discipline): void
    {
        $data = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'caseType' => ['required', 'string', 'max:150'],
            'severity' => ['required', 'in:low,medium,high'],
            'description' => ['required', 'string', 'max:5000'],
            'incidentDate' => ['required', 'date'],
        ]);

        try {
            $case = $discipline->raise(Employee::findOrFail($data['employeeId']), auth()->user(), [
                'caseType' => $data['caseType'],
                'severity' => $data['severity'],
                'description' => $data['description'],
                'incidentDate' => $data['incidentDate'],
            ]);
        } catch (ValidationException $e) {
            $this->addError('employeeId', $e->errors()['employee'][0]);

            return;
        }

        if ($this->file) {
            $case->addMedia($this->file->getRealPath())->usingName($this->file->getClientOriginalName())->toMediaCollection('attachments');
        }

        $this->reset('creating', 'employeeId', 'caseType', 'description', 'file');
        $this->severity = 'low';
        session()->flash('status', 'Case raised.');
    }

    public function respond(int $id, DisciplinaryCaseService $discipline): void
    {
        $data = $this->validate(['responseBody' => ['required', 'string', 'max:5000']]);
        $case = DisciplinaryCase::findOrFail($id);
        $response = $discipline->respond($case, auth()->user(), $data['responseBody']);

        if ($this->file) {
            $response->addMedia($this->file->getRealPath())->usingName($this->file->getClientOriginalName())->toMediaCollection('attachments');
        }

        $this->reset('respondingId', 'responseBody', 'file');
        session()->flash('status', 'Response submitted.');
    }

    public function resolve(int $id, DisciplinaryCaseService $discipline): void
    {
        $data = $this->validate([
            'outcome' => ['required', 'in:'.implode(',', DisciplinaryCase::OUTCOMES)],
            'resolutionNote' => ['nullable', 'string', 'max:5000'],
        ]);

        $case = DisciplinaryCase::findOrFail($id);
        $discipline->resolve($case, auth()->user(), $data['outcome'], $data['resolutionNote'] ?: null);

        if ($this->file) {
            $case->addMedia($this->file->getRealPath())->usingName($this->file->getClientOriginalName())->toMediaCollection('attachments');
        }

        $this->reset('resolvingId', 'outcome', 'resolutionNote', 'file');
        session()->flash('status', 'Case resolved.');
    }

    public function followUp(int $id, DisciplinaryCaseService $discipline): void
    {
        $data = $this->validate(['followUpBody' => ['required', 'string', 'max:5000']]);
        $discipline->followUp(DisciplinaryCase::findOrFail($id), auth()->user(), $data['followUpBody']);
        $this->reset('followingUpId', 'followUpBody');
        session()->flash('status', 'Follow-up question raised.');
    }

    public function acknowledge(int $id, DisciplinaryCaseService $discipline, SignatureService $signatures): void
    {
        try {
            $discipline->acknowledgeOutcome(DisciplinaryCase::findOrFail($id), auth()->user(), $signatures);
            session()->flash('status', 'Outcome acknowledged.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(PermissionService $permissions, WorkflowEngine $workflow, SignatureService $signatures): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'disciplinary_case');

        $cases = DisciplinaryCase::with(['employee', 'raisedBy', 'responses.respondedBy'])
            ->when($scope === 'self', fn ($q) => $q->where('employee_id', $me?->id))
            ->when($scope === 'self_subordinates', fn ($q) => $q->where(fn ($q2) => $q2->where('employee_id', $me?->id)->orWhere('raised_by', $me?->id)->orWhereHas('employee', fn ($e) => $e->where('supervisor_id', $me?->id))))
            ->latest()
            ->get();

        $canRaise = in_array($scope, ['all', 'self_subordinates'], true);

        $raisableEmployees = $scope === 'all'
            ? Employee::orderBy('last_name')->get()
            : ($me ? $me->subordinates()->orderBy('last_name')->get() : collect());

        return [
            'cases' => $cases,
            'canRaise' => $canRaise,
            'raisableEmployees' => $raisableEmployees,
            'availableActions' => fn ($case) => $workflow->availableTransitions('disciplinary_case', $case->status, $user, $case),
            'canEditCase' => fn ($case) => $case->status === 'open' && $me && $case->raised_by === $me->id,
            'isSubject' => fn ($case) => $me && $case->employee_id === $me->id,
            'hasAcknowledged' => fn ($case) => $case->outcomeRequiresSignature() && $me && $signatures->hasValidSignature($case, $user, 'disciplinary_outcome_acknowledgement', "Disciplinary case #{$case->id} outcome acknowledgement: {$case->outcomeLabel()}."),
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

    @if($canRaise)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            @if(! $creating)
                <button wire:click="$set('creating', true)" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Raise a case</button>
            @else
                <h2 class="mb-3.5 font-display text-base font-bold text-text">Raise a case</h2>
                <form wire:submit="raise" class="flex flex-col gap-3.5">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                            <select wire:model="employeeId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                <option value="">— select —</option>
                                @foreach($raisableEmployees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                            </select>
                            @error('employeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-text">Case type</label>
                            <input type="text" wire:model="caseType" placeholder="e.g. Attendance, Conduct" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                            @error('caseType') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-text">Severity</label>
                            <select wire:model="severity" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Incident date</label>
                        <input type="date" wire:model="incidentDate" class="w-48 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                        @error('description') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Supporting evidence <span class="text-text-muted" style="font-weight:400;">(optional)</span></label>
                        <input type="file" wire:model="file" class="text-sm text-text">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Submit case</button>
                        <button type="button" wire:click="$set('creating', false)" class="text-sm font-semibold text-text-muted">Cancel</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    <div class="flex flex-col gap-3">
        @foreach($cases as $case)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button wire:click="$set('expandedId', {{ $expandedId === $case->id ? 'null' : $case->id }})" class="text-left">
                        <div class="font-display text-sm font-bold text-text">{{ $case->case_type }} — {{ $case->employee->fullName() }}</div>
                        <div class="text-xs text-text-muted">{{ ucfirst($case->severity) }} severity · incident {{ $case->incident_date->format('j M Y') }} · raised by {{ $case->raisedBy->fullName() }}</div>
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text">{{ ucfirst($case->status) }}</span>
                        @if($case->outcome)
                            <span class="rounded-pill bg-danger/10 px-2.5 py-1 text-xs font-semibold text-danger">{{ $case->outcomeLabel() }}</span>
                        @endif
                    </div>
                </div>

                @if($expandedId === $case->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        <p class="text-sm text-text">{{ $case->description }}</p>

                        @foreach($case->getMedia('attachments') as $media)
                            <a href="{{ $media->getUrl() }}" target="_blank" class="self-start rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text hover:bg-text-faint/25">{{ $media->name }}</a>
                        @endforeach

                        @if($case->responses->isNotEmpty())
                            <div class="flex flex-col gap-2 border-t border-border pt-3">
                                @foreach($case->responses as $response)
                                    <div class="rounded-sm border border-border bg-bg p-3">
                                        <div class="mb-1 text-xs font-semibold text-text">{{ $response->type === 'follow_up' ? 'HR follow-up' : 'Response' }} — {{ $response->respondedBy->name }}</div>
                                        <div class="text-sm text-text">{{ $response->body }}</div>
                                        @foreach($response->getMedia('attachments') as $media)
                                            <a href="{{ $media->getUrl() }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-primary">{{ $media->name }}</a>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($case->resolution_note)
                            <div class="text-xs text-text-muted"><span class="font-semibold text-text">Resolution note:</span> {{ $case->resolution_note }}</div>
                        @endif

                        @if($case->outcomeRequiresSignature() && $isSubject($case))
                            @if($hasAcknowledged($case))
                                <div class="text-xs font-semibold text-accent">You have acknowledged this outcome.</div>
                            @else
                                <button wire:click="acknowledge({{ $case->id }})" class="self-start rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Acknowledge &amp; sign</button>
                            @endif
                        @endif

                        @foreach($availableActions($case) as $transition)
                            @if($transition->action === 'respond' && $respondingId !== $case->id)
                                <button wire:click="$set('respondingId', {{ $case->id }})" class="self-start text-xs font-semibold text-primary">Respond</button>
                            @elseif($transition->action === 'resolve' && $resolvingId !== $case->id)
                                <button wire:click="$set('resolvingId', {{ $case->id }})" class="self-start text-xs font-semibold text-primary">Resolve</button>
                            @elseif($transition->action === 'follow_up' && $followingUpId !== $case->id)
                                <button wire:click="$set('followingUpId', {{ $case->id }})" class="self-start text-xs font-semibold text-primary">Raise follow-up</button>
                            @endif
                        @endforeach

                        @if($respondingId === $case->id)
                            <div class="rounded-sm border border-border bg-bg p-3.5">
                                <textarea wire:model="responseBody" rows="2" placeholder="Your response" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary"></textarea>
                                @error('responseBody') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                <input type="file" wire:model="file" class="mt-2 text-xs">
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="respond({{ $case->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Submit</button>
                                    <button wire:click="$set('respondingId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                </div>
                            </div>
                        @endif

                        @if($resolvingId === $case->id)
                            <div class="rounded-sm border border-border bg-bg p-3.5">
                                <label class="mb-1.5 block text-xs font-semibold text-text">Outcome</label>
                                <select wire:model="outcome" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                    <option value="">— select —</option>
                                    @foreach(\App\Models\DisciplinaryCase::OUTCOMES as $o)
                                        <option value="{{ $o }}">{{ ucfirst(str_replace('_', ' ', $o)) }}</option>
                                    @endforeach
                                </select>
                                @error('outcome') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                <textarea wire:model="resolutionNote" rows="2" placeholder="Resolution note (optional)" class="mt-2 w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary"></textarea>
                                <input type="file" wire:model="file" class="mt-2 text-xs">
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="resolve({{ $case->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Resolve</button>
                                    <button wire:click="$set('resolvingId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                </div>
                            </div>
                        @endif

                        @if($followingUpId === $case->id)
                            <div class="rounded-sm border border-border bg-bg p-3.5">
                                <textarea wire:model="followUpBody" rows="2" placeholder="Follow-up question" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary"></textarea>
                                @error('followUpBody') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="followUp({{ $case->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Submit follow-up</button>
                                    <button wire:click="$set('followingUpId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach
        @if($cases->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No disciplinary cases.</div>
        @endif
    </div>
</div>
