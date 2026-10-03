<?php

use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Livewire\Component;

/**
 * Split out of employee-career-tab (which keeps onboarding/offboarding
 * tasks — an HR-initiated workflow, not something an employee should
 * self-assign). Development plans and training records take a plain
 * Employee with no admin-only gating, same as employee-personal-tab and
 * employee-contact-tab, so this is reusable on both the HR-facing employee
 * editor and the self-service My Info page (backlog #10).
 */
new class extends Component
{
    public Employee $employee;

    public string $planGoal = '';

    public string $planTargetSkill = '';

    public string $planTargetDate = '';

    public string $trainingTitle = '';

    public string $trainingProvider = '';

    public string $trainingCategory = '';

    public string $trainingStart = '';

    public string $trainingEnd = '';

    public string $trainingStatus = 'planned';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function addPlan(): void
    {
        $this->validate(['planGoal' => ['required', 'string', 'max:200']]);

        $this->employee->developmentPlans()->create([
            'goal' => $this->planGoal,
            'target_skill_or_role' => $this->planTargetSkill ?: null,
            'target_date' => $this->planTargetDate ?: null,
        ]);

        $this->reset('planGoal', 'planTargetSkill', 'planTargetDate');
        session()->flash('status', 'Development plan added.');
    }

    public function setPlanStatus(int $planId, string $status): void
    {
        DevelopmentPlan::where('employee_id', $this->employee->id)->findOrFail($planId)->update(['status' => $status]);
    }

    public function deletePlan(int $planId): void
    {
        DevelopmentPlan::where('employee_id', $this->employee->id)->findOrFail($planId)->delete();
    }

    public function addTraining(): void
    {
        $this->validate(['trainingTitle' => ['required', 'string', 'max:200']]);

        $this->employee->trainingRecords()->create([
            'title' => $this->trainingTitle,
            'provider' => $this->trainingProvider ?: null,
            'category' => $this->trainingCategory ?: null,
            'start_date' => $this->trainingStart ?: null,
            'end_date' => $this->trainingEnd ?: null,
            'status' => $this->trainingStatus,
        ]);

        $this->reset('trainingTitle', 'trainingProvider', 'trainingCategory', 'trainingStart', 'trainingEnd');
        $this->trainingStatus = 'planned';
        session()->flash('status', 'Training record added.');
    }

    public function deleteTraining(int $id): void
    {
        $record = TrainingRecord::where('employee_id', $this->employee->id)->findOrFail($id);
        abort_unless($record->canBeEditedBy(auth()->user()), 403);
        $record->delete();
    }

    public function with(): array
    {
        return [
            'plans' => $this->employee->developmentPlans()->orderByDesc('target_date')->get(),
            'trainingRecords' => $this->employee->trainingRecords()->orderByDesc('start_date')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Development plans</h2>
        <form wire:submit="addPlan" class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-4 md:items-end">
            <input type="text" wire:model="planGoal" placeholder="Goal" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="planTargetSkill" placeholder="Target skill/role" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="planTargetDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('planGoal') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        <div class="divide-y divide-border">
            @foreach($plans as $plan)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $plan->goal }} @if($plan->target_skill_or_role)<span class="text-text-muted">→ {{ $plan->target_skill_or_role }}</span>@endif @if($plan->target_date)<span class="text-xs text-text-faint">· by {{ $plan->target_date->format('j M Y') }}</span>@endif</span>
                    <div class="flex items-center gap-2">
                        <select wire:change="setPlanStatus({{ $plan->id }}, $event.target.value)" class="rounded-sm border border-border bg-surface px-1.5 py-1 text-xs text-text">
                            @foreach(\App\Models\DevelopmentPlan::STATUSES as $st)<option value="{{ $st }}" @selected($plan->status === $st)>{{ ucfirst(str_replace('_',' ',$st)) }}</option>@endforeach
                        </select>
                        <button wire:click="deletePlan({{ $plan->id }})" class="text-xs font-semibold text-danger">×</button>
                    </div>
                </div>
            @endforeach
            @if($plans->isEmpty())<div class="py-3 text-center text-sm text-text-muted">No development plans yet.</div>@endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Training records</h2>
        <form wire:submit="addTraining" class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-6 md:items-end">
            <input type="text" wire:model="trainingTitle" placeholder="Title" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="trainingProvider" placeholder="Provider" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="trainingCategory" placeholder="Category" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="trainingStart" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="trainingEnd" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('trainingTitle') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        <div class="divide-y divide-border">
            @foreach($trainingRecords as $record)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $record->title }} @if($record->provider)<span class="text-text-muted">· {{ $record->provider }}</span>@endif <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ ucfirst($record->status) }}</span></span>
                    @if($record->canBeEditedBy(auth()->user()))
                        <button wire:click="deleteTraining({{ $record->id }})" wire:confirm="Delete this training record?" class="text-xs font-semibold text-danger">Delete</button>
                    @endif
                </div>
            @endforeach
            @if($trainingRecords->isEmpty())<div class="py-3 text-center text-sm text-text-muted">No training records yet.</div>@endif
        </div>
    </section>
</div>
