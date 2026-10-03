<?php

use App\Models\Employee;
use App\Models\EmployeeTask;
use App\Models\OnboardingOffboardingTemplate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Spec B3: onboarding/offboarding task checklists — an HR-initiated
 * workflow (applying a template, assigning ad hoc tasks), kept separate
 * from employee-development-tab's development plans/training records so
 * the latter can be safely reused on the self-service My Info page without
 * also handing employees the ability to assign themselves onboarding/
 * offboarding tasks.
 */
new class extends Component
{
    use WithFileUploads;

    public Employee $employee;

    public ?int $applyTemplateId = null;

    public string $adHocTitle = '';

    public string $adHocKind = 'onboarding';

    public string $adHocDueDate = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    /** Spec B3: "apply template bulk task generation with computed due dates" — offset_days from join date (onboarding) or latest termination date (offboarding). */
    public function applyTemplate(): void
    {
        $this->validate(['applyTemplateId' => ['required', 'exists:onboarding_offboarding_templates,id']]);

        $template = OnboardingOffboardingTemplate::with('items')->findOrFail($this->applyTemplateId);

        $anchor = $template->type === 'onboarding'
            ? $this->employee->hire_date
            : $this->employee->terminations()->latest('date')->value('date');

        if (! $anchor) {
            $this->addError('applyTemplateId', $template->type === 'offboarding'
                ? 'This employee has no termination record to anchor offboarding due dates to.'
                : 'This employee has no hire date to anchor onboarding due dates to.');

            return;
        }

        foreach ($template->items as $item) {
            EmployeeTask::create([
                'employee_id' => $this->employee->id,
                'template_item_id' => $item->id,
                'kind' => $template->type,
                'title' => $item->title,
                'due_date' => \Illuminate\Support\Carbon::parse($anchor)->addDays($item->offset_days),
            ]);
        }

        $this->reset('applyTemplateId');
        session()->flash('status', "Applied \"{$template->name}\" — {$template->items->count()} tasks created.");
    }

    public function addAdHocTask(): void
    {
        $this->validate([
            'adHocTitle' => ['required', 'string', 'max:150'],
            'adHocKind' => ['required', 'in:onboarding,offboarding'],
            'adHocDueDate' => ['nullable', 'date'],
        ]);

        EmployeeTask::create([
            'employee_id' => $this->employee->id,
            'kind' => $this->adHocKind,
            'title' => $this->adHocTitle,
            'due_date' => $this->adHocDueDate ?: null,
        ]);

        $this->reset('adHocTitle', 'adHocDueDate');
        session()->flash('status', 'Task added.');
    }

    public function setTaskStatus(int $taskId, string $status): void
    {
        $task = EmployeeTask::where('employee_id', $this->employee->id)->findOrFail($taskId);
        $task->update(['status' => $status, 'completed_at' => $status === 'done' ? now() : null]);
    }

    public function deleteTask(int $taskId): void
    {
        EmployeeTask::where('employee_id', $this->employee->id)->findOrFail($taskId)->delete();
    }

    public function with(): array
    {
        return [
            'onboardingTasks' => $this->employee->onboardingTasks()->orderBy('due_date')->get(),
            'offboardingTasks' => $this->employee->offboardingTasks()->orderBy('due_date')->get(),
            'templates' => OnboardingOffboardingTemplate::orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Onboarding / offboarding tasks</h2>
        <form wire:submit="applyTemplate" class="mb-3 flex flex-wrap items-end gap-3">
            <div class="min-w-[200px] flex-1">
                <label class="mb-1.5 block text-xs font-semibold text-text">Apply a template</label>
                <select wire:model="applyTemplateId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">— select template —</option>
                    @foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ ucfirst($t->type) }})</option>@endforeach
                </select>
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Apply</button>
        </form>
        @error('applyTemplateId') <div class="mb-3 text-xs text-danger">{{ $message }}</div> @enderror

        <form wire:submit="addAdHocTask" class="mb-4 flex flex-wrap items-end gap-3">
            <input type="text" wire:model="adHocTitle" placeholder="Ad hoc task title" class="min-w-[180px] flex-1 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <select wire:model="adHocKind" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="onboarding">Onboarding</option>
                <option value="offboarding">Offboarding</option>
            </select>
            <input type="date" wire:model="adHocDueDate" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add task</button>
        </form>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">Onboarding</h3>
                <div class="divide-y divide-border">
                    @foreach($onboardingTasks as $task)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="{{ $task->status === 'done' ? 'text-text-muted line-through' : 'text-text' }}">{{ $task->title }} @if($task->due_date)<span class="text-xs text-text-faint">· due {{ $task->due_date->format('j M Y') }}</span>@endif</span>
                            <div class="flex items-center gap-2">
                                <select wire:change="setTaskStatus({{ $task->id }}, $event.target.value)" class="rounded-sm border border-border bg-surface px-1.5 py-1 text-xs text-text">
                                    @foreach(\App\Models\EmployeeTask::STATUSES as $st)<option value="{{ $st }}" @selected($task->status === $st)>{{ ucfirst(str_replace('_',' ',$st)) }}</option>@endforeach
                                </select>
                                <button wire:click="deleteTask({{ $task->id }})" class="text-xs font-semibold text-danger">×</button>
                            </div>
                        </div>
                    @endforeach
                    @if($onboardingTasks->isEmpty())<div class="py-3 text-center text-xs text-text-muted">No onboarding tasks.</div>@endif
                </div>
            </div>
            <div>
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">Offboarding</h3>
                <div class="divide-y divide-border">
                    @foreach($offboardingTasks as $task)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="{{ $task->status === 'done' ? 'text-text-muted line-through' : 'text-text' }}">{{ $task->title }} @if($task->due_date)<span class="text-xs text-text-faint">· due {{ $task->due_date->format('j M Y') }}</span>@endif</span>
                            <div class="flex items-center gap-2">
                                <select wire:change="setTaskStatus({{ $task->id }}, $event.target.value)" class="rounded-sm border border-border bg-surface px-1.5 py-1 text-xs text-text">
                                    @foreach(\App\Models\EmployeeTask::STATUSES as $st)<option value="{{ $st }}" @selected($task->status === $st)>{{ ucfirst(str_replace('_',' ',$st)) }}</option>@endforeach
                                </select>
                                <button wire:click="deleteTask({{ $task->id }})" class="text-xs font-semibold text-danger">×</button>
                            </div>
                        </div>
                    @endforeach
                    @if($offboardingTasks->isEmpty())<div class="py-3 text-center text-xs text-text-muted">No offboarding tasks.</div>@endif
                </div>
            </div>
        </div>
    </section>
</div>
