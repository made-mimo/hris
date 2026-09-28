<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Requisition;
use App\Services\PermissionService;
use App\Services\RecruitmentService;
use App\Services\WorkflowEngine;
use Livewire\Component;

/** Spec D1: "Requisition workflow (optional pre-vacancy approval gate): Requested → Approved (auto-creates the vacancy) or Rejected, a one-time, one-way decision." */
new class extends Component
{
    public string $title = '';

    public ?int $jobTitleId = null;

    public int $positionCount = 1;

    public string $justification = '';

    public ?int $hiringManagerId = null;

    public ?int $decidingId = null;

    public string $decisionComment = '';

    public function create(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'jobTitleId' => ['nullable', 'exists:job_titles,id'],
            'positionCount' => ['required', 'integer', 'min:1'],
            'justification' => ['required', 'string', 'max:2000'],
            'hiringManagerId' => ['required', 'exists:employees,id'],
        ]);

        Requisition::create([
            'title' => $data['title'],
            'job_title_id' => $data['jobTitleId'],
            'position_count' => $data['positionCount'],
            'justification' => $data['justification'],
            'requested_by' => auth()->id(),
            'hiring_manager_id' => $data['hiringManagerId'],
            'status' => 'requested',
        ]);

        $this->reset('title', 'jobTitleId', 'positionCount', 'justification', 'hiringManagerId');
        session()->flash('status', 'Requisition submitted.');
    }

    public function decide(int $id, string $action, RecruitmentService $recruitment): void
    {
        $requisition = Requisition::findOrFail($id);
        $recruitment->decideRequisition($requisition, auth()->user(), $action, $this->decisionComment ?: null);
        $this->reset('decidingId', 'decisionComment');
        session()->flash('status', $action === 'approve' ? 'Requisition approved — vacancy created.' : 'Requisition rejected.');
    }

    public function with(PermissionService $permissions, WorkflowEngine $workflow): array
    {
        $user = auth()->user();
        $scope = $permissions->scopeFor($user, 'recruitment');
        $me = $user->employee;

        $requisitions = Requisition::with(['requestedBy', 'hiringManager', 'jobTitle', 'vacancy'])
            ->when($scope !== 'all', fn ($q) => $q->where('hiring_manager_id', $me?->id))
            ->latest()
            ->get();

        return [
            'requisitions' => $requisitions,
            'canDecide' => fn ($r) => $workflow->availableTransitions('requisition', $r->status, $user, $r)->isNotEmpty(),
            'employees' => Employee::orderBy('last_name')->get(),
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New requisition</h2>
        <form wire:submit="create" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Position title</label>
                    <input type="text" wire:model="title" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('title') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Job title</label>
                    <select wire:model="jobTitleId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— none —</option>
                        @foreach($jobTitles as $jt)<option value="{{ $jt->id }}">{{ $jt->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Number of positions</label>
                    <input type="number" min="1" wire:model="positionCount" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('positionCount') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Hiring manager</label>
                    <select wire:model="hiringManagerId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                    </select>
                    @error('hiringManagerId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Justification</label>
                <textarea wire:model="justification" rows="2" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                @error('justification') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Submit requisition</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Title</th>
                    <th class="px-4 py-2.5">Positions</th>
                    <th class="px-4 py-2.5">Hiring manager</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5">Vacancy</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($requisitions as $r)
                    <tr class="border-b border-border last:border-0 align-top">
                        <td class="px-4 py-2.5 text-text">{{ $r->title }}<div class="text-xs text-text-muted">{{ $r->justification }}</div></td>
                        <td class="px-4 py-2.5 text-text">{{ $r->position_count }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $r->hiringManager->fullName() }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text">{{ ucfirst($r->status) }}</span></td>
                        <td class="px-4 py-2.5 text-text">{{ $r->vacancy?->title ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if($canDecide($r))
                                @if($decidingId === $r->id)
                                    <div class="flex flex-col gap-2">
                                        <input type="text" wire:model="decisionComment" placeholder="Comment (optional)" class="rounded-sm border border-border bg-surface px-2 py-1 text-xs text-text outline-none focus:border-primary">
                                        <div class="flex gap-2">
                                            <button wire:click="decide({{ $r->id }}, 'approve')" class="text-xs font-semibold text-accent">Approve</button>
                                            <button wire:click="decide({{ $r->id }}, 'reject')" class="text-xs font-semibold text-danger">Reject</button>
                                            <button wire:click="$set('decidingId', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                                        </div>
                                    </div>
                                @else
                                    <button wire:click="$set('decidingId', {{ $r->id }})" class="text-xs font-semibold text-primary">Decide</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if($requisitions->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="6">No requisitions yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
