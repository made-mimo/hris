<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\PerformanceReview;
use App\Services\PerformanceService;
use App\Services\PermissionService;
use App\Services\SignatureService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Spec D2's formal cyclical Performance Review — dual independent Supervisor/Self tracks, KPI-gated activation, e-signature-backed reviewer sign-off. */
new class extends Component
{
    public bool $includePast = false;

    public ?int $employeeId = null;

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $dueDate = '';

    public ?int $expandedId = null;

    public array $ratingInputs = [];

    public string $finalRating = '';

    public string $finalComment = '';

    public function mount(): void
    {
        $this->periodStart = now()->startOfYear()->toDateString();
        $this->periodEnd = now()->endOfYear()->toDateString();
        $this->dueDate = now()->addMonth()->toDateString();
    }

    public function create(): void
    {
        $data = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after:periodStart'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $employee = Employee::findOrFail($data['employeeId']);

        PerformanceReview::create([
            'employee_id' => $employee->id,
            'job_title_id' => $employee->job_title_id,
            'sub_unit_id' => $employee->sub_unit_id,
            'review_period_start' => $data['periodStart'],
            'review_period_end' => $data['periodEnd'],
            'due_date' => $data['dueDate'] ?: null,
            'status' => 'inactive',
        ]);

        session()->flash('status', 'Review created — activate it once ready.');
    }

    public function activate(int $id, PerformanceService $performance): void
    {
        try {
            $performance->activate(PerformanceReview::findOrFail($id));
            session()->flash('status', 'Review activated — both reviewer tracks are ready.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['activate'][0]);
        }
    }

    public function startRating(int $reviewerId): void
    {
        $reviewer = \App\Models\PerformanceReviewer::with('ratings')->findOrFail($reviewerId);
        $this->ratingInputs = $reviewer->ratings->mapWithKeys(fn ($r) => [$r->kpi_id => $r->rating])->all();
    }

    public function saveRatings(int $reviewerId, PerformanceService $performance): void
    {
        $reviewer = \App\Models\PerformanceReviewer::findOrFail($reviewerId);
        $performance->saveRatings($reviewer, $this->ratingInputs);
        $this->reset('ratingInputs');
        session()->flash('status', 'Ratings saved.');
    }

    public function signOff(int $reviewerId, PerformanceService $performance, SignatureService $signatures): void
    {
        try {
            $performance->signOff(\App\Models\PerformanceReviewer::findOrFail($reviewerId), auth()->user(), $signatures);
            session()->flash('status', 'Signed off.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function finalize(int $id, PerformanceService $performance): void
    {
        $data = $this->validate([
            'finalRating' => ['required', 'numeric', 'min:0', 'max:100'],
            'finalComment' => ['nullable', 'string', 'max:2000'],
        ]);

        $performance->finalize(PerformanceReview::findOrFail($id), (float) $data['finalRating'], $data['finalComment'] ?: null);
        $this->reset('finalRating', 'finalComment');
        session()->flash('status', 'Review finalized.');
    }

    public function with(PermissionService $permissions): array
    {
        $user = auth()->user();
        $me = $user->employee;
        $scope = $permissions->scopeFor($user, 'performance');

        $reviews = PerformanceReview::with(['employee', 'jobTitle', 'reviewers.employee', 'reviewers.ratings.kpi'])
            ->when($scope === 'self', fn ($q) => $q->where('employee_id', $me?->id))
            ->when($scope === 'self_subordinates', fn ($q) => $q->where(fn ($q2) => $q2->where('employee_id', $me?->id)->orWhereHas('employee', fn ($e) => $e->where('supervisor_id', $me?->id))))
            ->when(! $this->includePast, fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereDoesntHave('terminations')->where('is_gdpr_purged', false)))
            ->latest()
            ->get();

        $canManage = in_array($scope, ['all', 'self_subordinates'], true);

        $employees = $scope === 'all'
            ? Employee::orderBy('last_name')->get()
            : Employee::where('id', $me?->id)->orWhere('supervisor_id', $me?->id)->orderBy('last_name')->get();

        return [
            'reviews' => $reviews,
            'canManage' => $canManage,
            'employees' => $employees,
            'myEmployeeId' => $me?->id,
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

    @if($canManage)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">New review</h2>
            <form wire:submit="create" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                    <select wire:model="employeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                    </select>
                    @error('employeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Period start</label>
                    <input type="date" wire:model="periodStart" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Period end</label>
                    <input type="date" wire:model="periodEnd" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('periodEnd') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Due date</label>
                    <input type="date" wire:model="dueDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Create review</button>
            </form>
        </section>
    @endif

    <label class="flex items-center gap-1.5 self-start text-xs text-text">
        <input type="checkbox" wire:model.live="includePast" class="h-4 w-4 accent-primary">
        Include terminated/past employees
    </label>

    <div class="flex flex-col gap-3">
        @foreach($reviews as $review)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button wire:click="$set('expandedId', {{ $expandedId === $review->id ? 'null' : $review->id }})" class="text-left">
                        <div class="font-display text-sm font-bold text-text">{{ $review->employee->fullName() }}</div>
                        <div class="text-xs text-text-muted">{{ $review->review_period_start->format('j M Y') }} – {{ $review->review_period_end->format('j M Y') }}</div>
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text">{{ ucfirst(str_replace('_', ' ', $review->status)) }}</span>
                        @if($review->status === 'inactive' && $canManage)
                            <button wire:click="activate({{ $review->id }})" class="rounded-sm border border-border px-2.5 py-1 text-xs font-semibold text-text hover:bg-bg">Activate</button>
                        @endif
                        @if($review->final_rating)
                            <span class="rounded-pill bg-accent-light px-2.5 py-1 text-xs font-semibold text-accent">{{ $review->final_rating }}/100</span>
                        @endif
                    </div>
                </div>

                @if($expandedId === $review->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        @foreach($review->reviewers as $reviewer)
                            <div class="rounded-sm border border-border bg-bg p-3.5">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-text">{{ ucfirst($reviewer->group) }} — {{ $reviewer->employee->fullName() }}</span>
                                    <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ ucfirst(str_replace('_', ' ', $reviewer->status)) }}</span>
                                </div>

                                @if($reviewer->employee_id === $myEmployeeId && $reviewer->status !== 'completed')
                                    @if(empty($ratingInputs))
                                        <button wire:click="startRating({{ $reviewer->id }})" class="text-xs font-semibold text-primary">Rate KPIs</button>
                                    @else
                                        @foreach($reviewer->ratings as $rating)
                                            <div class="mb-1.5 flex items-center justify-between gap-2 text-xs">
                                                <span class="text-text">{{ $rating->kpi->title }} <span class="text-text-muted">({{ $rating->kpi->min_scale }}–{{ $rating->kpi->max_scale }})</span></span>
                                                <input type="number" step="0.5" min="{{ $rating->kpi->min_scale }}" max="{{ $rating->kpi->max_scale }}" wire:model="ratingInputs.{{ $rating->kpi_id }}" class="w-20 rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                            </div>
                                        @endforeach
                                        <div class="mt-2 flex gap-2">
                                            <button wire:click="saveRatings({{ $reviewer->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save ratings</button>
                                            <button wire:click="signOff({{ $reviewer->id }})" class="rounded-sm border border-border px-3 py-1.5 text-xs font-semibold text-text hover:bg-surface">Sign off</button>
                                        </div>
                                    @endif
                                @else
                                    @foreach($reviewer->ratings as $rating)
                                        <div class="mb-1 flex items-center justify-between text-xs text-text-muted">
                                            <span>{{ $rating->kpi->title }}</span>
                                            <span class="font-mono">{{ $rating->rating ?? '—' }}</span>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach

                        @if($canManage && $review->status !== 'completed' && $review->reviewers->every(fn($r) => $r->status === 'completed') && $review->reviewers->isNotEmpty())
                            <div class="rounded-sm border border-accent/30 bg-accent-light p-3.5">
                                <div class="mb-2 text-xs font-semibold text-text">Final evaluation</div>
                                <div class="flex flex-wrap items-end gap-2">
                                    <input type="number" min="0" max="100" step="0.5" wire:model="finalRating" placeholder="Overall rating (0-100)" class="w-48 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    <input type="text" wire:model="finalComment" placeholder="Final comment" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    <button wire:click="finalize({{ $review->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Finalize</button>
                                </div>
                                @error('finalRating') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        @if($review->final_comment)
                            <div class="text-xs text-text-muted"><span class="font-semibold text-text">Final comment:</span> {{ $review->final_comment }}</div>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach
        @if($reviews->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No performance reviews yet.</div>
        @endif
    </div>
</div>
