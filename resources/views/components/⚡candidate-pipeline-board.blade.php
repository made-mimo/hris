<?php

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Employee;
use App\Models\Vacancy;
use App\Services\PermissionService;
use App\Services\RecruitmentService;
use App\Services\SignatureService;
use App\Services\WorkflowEngine;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Spec D1's candidate pipeline board: every application the current user may act on or view, with the workflow engine driving which actions are offered per record. */
new class extends Component
{
    use WithFileUploads;

    public ?int $vacancyId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $applicationMode = 'manual';

    public bool $consentGiven = false;

    public ?int $expandedId = null;

    public ?int $schedulingId = null;

    public string $interviewName = '';

    public string $interviewDate = '';

    public string $interviewTime = '';

    public array $interviewerIds = [];

    public $file = null;

    public function createApplication(): void
    {
        $data = $this->validate([
            'vacancyId' => ['required', 'exists:vacancies,id'],
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'applicationMode' => ['required', 'in:manual,online'],
            'consentGiven' => ['accepted'],
        ]);

        $candidate = Candidate::create([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'application_mode' => $data['applicationMode'],
            'application_date' => now(),
            'consent_given' => true,
        ]);

        CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'vacancy_id' => $data['vacancyId'],
            'status' => 'application_initiated',
        ]);

        $this->reset('vacancyId', 'firstName', 'lastName', 'email', 'phone', 'consentGiven');
        session()->flash('status', 'Candidate application added.');
    }

    public function act(int $id, string $action, RecruitmentService $recruitment): void
    {
        $recruitment->applyPipelineAction(CandidateApplication::findOrFail($id), auth()->user(), $action);
        session()->flash('status', 'Pipeline updated.');
    }

    public function startScheduling(int $id): void
    {
        $this->schedulingId = $id;
        $this->interviewName = 'Interview';
        $this->interviewDate = now()->addDays(3)->toDateString();
        $this->interviewTime = '';
        $this->interviewerIds = [];
    }

    public function scheduleInterview(RecruitmentService $recruitment): void
    {
        $data = $this->validate([
            'interviewName' => ['required', 'string', 'max:150'],
            'interviewDate' => ['required', 'date'],
            'interviewTime' => ['nullable'],
            'interviewerIds' => ['array'],
        ]);

        try {
            $recruitment->scheduleInterview(CandidateApplication::findOrFail($this->schedulingId), auth()->user(), [
                'name' => $data['interviewName'],
                'interviewDate' => $data['interviewDate'],
                'interviewTime' => $data['interviewTime'] ?: null,
                'interviewerIds' => $data['interviewerIds'],
            ]);
        } catch (ValidationException $e) {
            $this->addError('interviewName', $e->errors()['interview'][0]);

            return;
        }

        $this->reset('schedulingId', 'interviewName', 'interviewDate', 'interviewTime', 'interviewerIds');
        session()->flash('status', 'Interview scheduled.');
    }

    public function uploadCv(int $candidateId): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:pdf,doc,docx,rtf,odt,txt', 'max:10240']]);

        Candidate::findOrFail($candidateId)->addMedia($this->file->getRealPath())
            ->usingName($this->file->getClientOriginalName())
            ->toMediaCollection('attachments');

        $this->reset('file');
        session()->flash('status', 'CV attached.');
    }

    /** Spec D1: the offer-letter e-signature link the candidate uses — no real outbound email in this dev environment (see NotificationService's own doc comment), so the link is surfaced directly for the hiring manager/HR to relay. */
    public function offerLink(int $applicationId): string
    {
        return URL::signedRoute('recruitment.offer.sign', ['application' => $applicationId]);
    }

    public function with(PermissionService $permissions, WorkflowEngine $workflow, SignatureService $signatures): array
    {
        $user = auth()->user();
        $scope = $permissions->scopeFor($user, 'recruitment');
        $me = $user->employee;

        $applications = CandidateApplication::with(['candidate', 'vacancy.hiringManager', 'interviews.interviewers', 'history.performedBy'])
            ->when($scope !== 'all', fn ($q) => $q->whereHas('vacancy', fn ($v) => $v->where('hiring_manager_id', $me?->id)))
            ->latest()
            ->get();

        return [
            'applications' => $applications,
            'availableActions' => fn ($app) => $workflow->availableTransitions('candidate_pipeline', $app->status, $user, $app),
            'vacancies' => Vacancy::where('is_open', true)
                ->when($scope !== 'all', fn ($q) => $q->where('hiring_manager_id', $me?->id))
                ->get(),
            'employees' => Employee::orderBy('last_name')->get(),
            'offerSignatures' => fn ($app) => $signatures->signaturesFor($app, 'recruitment_offer_letter'),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Add candidate application</h2>
        <form wire:submit="createApplication" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Vacancy</label>
                    <select wire:model="vacancyId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($vacancies as $v)<option value="{{ $v->id }}">{{ $v->title }}</option>@endforeach
                    </select>
                    @error('vacancyId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Application mode</label>
                    <select wire:model="applicationMode" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="manual">Manual (walk-in/referral)</option>
                        <option value="online">Online</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">First name</label>
                    <input type="text" wire:model="firstName" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('firstName') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Last name</label>
                    <input type="text" wire:model="lastName" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('lastName') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Email</label>
                    <input type="email" wire:model="email" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('email') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Phone</label>
                    <input type="tel" inputmode="numeric" wire:model="phone" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
            </div>
            <label class="flex items-center gap-1.5 text-xs text-text">
                <input type="checkbox" wire:model="consentGiven" class="h-4 w-4 accent-primary">
                Candidate has consented to data retention for this application.
            </label>
            @error('consentGiven') <div class="text-xs text-danger">{{ $message }}</div> @enderror
            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Add application</button>
        </form>
    </section>

    <div class="flex flex-col gap-3">
        @foreach($applications as $app)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <button wire:click="$set('expandedId', {{ $expandedId === $app->id ? 'null' : $app->id }})" class="text-left font-display text-sm font-bold text-text hover:text-primary">
                            {{ $app->candidate->fullName() }}
                        </button>
                        <div class="text-xs text-text-muted">{{ $app->vacancy->title }} · {{ $app->candidate->email }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text">{{ $app->stageLabel() }}</span>
                        @foreach($availableActions($app) as $transition)
                            @if($transition->action === 'schedule_interview')
                                <button wire:click="startScheduling({{ $app->id }})" class="rounded-sm border border-border px-2.5 py-1 text-xs font-semibold text-text hover:bg-bg">{{ $transition->label }}</button>
                            @else
                                <button wire:click="act({{ $app->id }}, '{{ $transition->action }}')" class="rounded-sm border border-border px-2.5 py-1 text-xs font-semibold text-text hover:bg-bg">{{ $transition->label }}</button>
                            @endif
                        @endforeach
                    </div>
                </div>

                @if($schedulingId === $app->id)
                    <div class="mt-3 rounded-sm border border-border bg-bg p-3.5">
                        <div class="mb-2 text-xs font-semibold text-text">Schedule interview (round {{ $app->interviews->count() + 1 }} of {{ \App\Models\Interview::MAX_ROUNDS_PER_APPLICATION }})</div>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" wire:model="interviewName" placeholder="Name" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                            <input type="date" wire:model="interviewDate" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                            <input type="time" wire:model="interviewTime" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                        </div>
                        @error('interviewName') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($employees as $e)
                                <label class="flex items-center gap-1 text-xs text-text">
                                    <input type="checkbox" wire:model="interviewerIds" value="{{ $e->id }}" class="h-3.5 w-3.5 accent-primary">
                                    {{ $e->fullName() }}
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-2 flex gap-2">
                            <button wire:click="scheduleInterview" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Schedule</button>
                            <button wire:click="$set('schedulingId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                        </div>
                    </div>
                @endif

                @if($app->status === 'job_offered')
                    <div class="mt-3 rounded-sm border border-info-light bg-info-light p-3.5 text-xs text-info">
                        Offer-letter signing link for {{ $app->candidate->fullName() }} (no live outbound email in this environment — relay this link directly):
                        <a href="{{ $this->offerLink($app->id) }}" target="_blank" class="break-all font-semibold underline">{{ $this->offerLink($app->id) }}</a>
                        @if($offerSignatures($app)->isNotEmpty())
                            <div class="mt-1 font-semibold text-accent">Signed by {{ $offerSignatures($app)->first()->signerDisplayName() }} on {{ $offerSignatures($app)->first()->signed_at->format('j M Y, H:i') }}.</div>
                        @endif
                    </div>
                @endif

                @if($expandedId === $app->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">Attachments</div>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach($app->candidate->getMedia('attachments') as $media)
                                    <a href="{{ route('private-media.show', $media) }}" target="_blank" class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text hover:bg-text-faint/25">{{ $media->name }}</a>
                                @endforeach
                                <x-file-input model="file" :selected="$file" label="Choose CV" />
                                <button wire:click="uploadCv({{ $app->candidate->id }})" class="text-xs font-semibold text-primary">Upload CV</button>
                            </div>
                        </div>

                        @if($app->interviews->isNotEmpty())
                            <div>
                                <div class="mb-1 text-xs font-semibold text-text">Interviews</div>
                                @foreach($app->interviews as $interview)
                                    <div class="text-xs text-text-muted">{{ $interview->name }} — {{ $interview->interview_date->format('j M Y') }}{{ $interview->interview_time ? ' '.$interview->interview_time : '' }} · Interviewers: {{ $interview->interviewers->map->fullName()->join(', ') ?: '—' }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div>
                            <div class="mb-1 text-xs font-semibold text-text">History</div>
                            @foreach($app->history as $entry)
                                <div class="text-xs text-text-muted">{{ $entry->created_at->format(\App\Support\Dates::DATE_TIME) }} — {{ $entry->performedBy->name }}: {{ ucfirst(str_replace('_', ' ', $entry->action)) }}{{ $entry->note ? " ({$entry->note})" : '' }}</div>
                            @endforeach
                            @if($app->history->isEmpty())
                                <div class="text-xs text-text-muted">No history yet.</div>
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($applications->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No candidate applications yet.</div>
        @endif
    </div>
</div>
