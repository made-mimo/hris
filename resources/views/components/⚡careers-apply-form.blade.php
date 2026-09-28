<?php

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Vacancy;
use App\Traits\ThrottlesAttempts;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Spec D1's public application form — "Attachment support with an allow-listed set of document types (PDF, Word, RTF, ODT, plain text) for CVs." */
new class extends Component
{
    use WithFileUploads;
    use ThrottlesAttempts;

    public Vacancy $vacancy;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public bool $consentGiven = false;

    public $cv = null;

    public bool $submitted = false;

    public string $rateLimitError = '';

    /**
     * Security fix: this is the one Livewire action in the app reachable
     * without logging in at all — a public careers page. Keyed by a fixed
     * scope string (not the submitted email, which an abuser fully
     * controls and would just rotate) so the limit is purely per-IP: 10
     * applications/hour is generous for a genuine one-time applicant and a
     * meaningful throttle on scripted submission.
     */
    public function apply(): void
    {
        if ($this->tooManyAttempts('careers-apply', 'careers-apply', 10)) {
            $seconds = $this->rateLimitSecondsRemaining('careers-apply', 'careers-apply');
            $minutes = (int) ceil($seconds / 60);
            $this->rateLimitError = "Too many applications from this connection recently. Please try again in about {$minutes} minute".($minutes === 1 ? '' : 's').'.';

            return;
        }
        $this->hitRateLimit('careers-apply', 'careers-apply', 3600);

        $data = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'consentGiven' => ['accepted'],
            'cv' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,rtf,odt,txt'],
        ]);

        $candidate = Candidate::create([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'application_mode' => 'online',
            'application_date' => now(),
            'consent_given' => true,
        ]);

        if ($this->cv) {
            $candidate->addMedia($this->cv->getRealPath())->usingName($this->cv->getClientOriginalName())->toMediaCollection('attachments');
        }

        CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'vacancy_id' => $this->vacancy->id,
            'status' => 'application_initiated',
        ]);

        $this->submitted = true;
    }
};
?>

<section class="rounded-md border border-border bg-surface p-5 shadow-sm">
    @if($submitted)
        <div class="text-sm text-accent" style="font-weight:600;">Thank you — your application has been received. We'll be in touch if you're shortlisted.</div>
    @else
        <h2 class="font-display text-base font-bold text-text" style="margin-bottom:14px;">Apply for this position</h2>
        @if($rateLimitError)
            <div class="mb-3.5 text-sm text-danger" style="font-weight:600;">{{ $rateLimitError }}</div>
        @endif
        <form wire:submit="apply" class="flex flex-col gap-3.5">
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
                    <input type="email" wire:model="email" placeholder="name@example.com" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('email') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Phone</label>
                    <input type="tel" inputmode="numeric" wire:model="phone" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">CV / resume <span class="text-text-muted" style="font-weight:400;">(PDF, Word, RTF, ODT, or plain text)</span></label>
                <input type="file" wire:model="cv" class="w-full text-sm text-text">
                @error('cv') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <label class="flex items-center gap-1.5 text-xs text-text">
                <input type="checkbox" wire:model="consentGiven" class="h-4 w-4 accent-primary">
                I consent to Systems Intelligenz retaining my application data for recruitment purposes.
            </label>
            @error('consentGiven') <div class="text-xs text-danger">{{ $message }}</div> @enderror
            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Submit application</button>
        </form>
    @endif
</section>
