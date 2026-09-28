<?php

use App\Models\Employee;
use App\Models\HelpdeskCategory;
use App\Models\Ticket;
use App\Services\PermissionService;
use App\Services\TicketService;
use Livewire\Component;

/**
 * Spec F4: "any employee may raise a ticket for themselves only"; "a ticket
 * and its comment thread are visible only to the raiser and to HR/Admin —
 * deliberately not to the raiser's manager chain"; confidential categories
 * narrow that further to a small, Admin-designated handler list.
 */
new class extends Component
{
    public bool $creating = false;

    public ?int $categoryId = null;

    public string $subject = '';

    public string $description = '';

    public bool $isAnonymous = false;

    public bool $mineOnly = false;

    public ?int $expandedId = null;

    public array $newComment = [];

    public ?int $assignEmployeeId = null;

    public array $revealedNames = [];

    public function createTicket(TicketService $tickets): void
    {
        $data = $this->validate([
            'categoryId' => ['required', 'exists:helpdesk_categories,id'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
        ]);

        $category = HelpdeskCategory::findOrFail($data['categoryId']);

        $tickets->raise(
            auth()->user()->employee,
            $category,
            $data['subject'],
            $data['description'],
            $category->is_confidential && $this->isAnonymous,
        );

        $this->reset('creating', 'categoryId', 'subject', 'description', 'isAnonymous');
        session()->flash('status', 'Ticket raised.');
    }

    public function addComment(int $ticketId, TicketService $tickets, PermissionService $permissions): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        abort_unless($tickets->canView($ticket, auth()->user()), 403);

        $body = trim($this->newComment[$ticketId] ?? '');
        if ($body === '') {
            return;
        }

        $tickets->addComment($ticket, auth()->user()->employee, $body);
        $this->newComment[$ticketId] = '';
    }

    public function startProgress(int $ticketId, TicketService $tickets): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        abort_unless($tickets->canManageStatus($ticket, auth()->user()), 403);
        $tickets->startProgress($ticket);
    }

    public function resolve(int $ticketId, TicketService $tickets): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        abort_unless($tickets->canManageStatus($ticket, auth()->user()), 403);
        $tickets->resolve($ticket);
    }

    public function close(int $ticketId, TicketService $tickets): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        abort_unless($tickets->canManageStatus($ticket, auth()->user()), 403);
        $tickets->close($ticket);
    }

    public function reopen(int $ticketId, TicketService $tickets): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        abort_unless($tickets->canManageStatus($ticket, auth()->user()), 403);
        $tickets->reopen($ticket);
    }

    public function assign(int $ticketId, TicketService $tickets): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        abort_unless($tickets->canManageStatus($ticket, auth()->user()), 403);

        if ($this->assignEmployeeId) {
            $tickets->assign($ticket, Employee::findOrFail($this->assignEmployeeId));
        }
        $this->reset('assignEmployeeId');
    }

    public function revealIdentity(int $ticketId, TicketService $tickets): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        $this->revealedNames[$ticketId] = $tickets->revealIdentity($ticket, auth()->user());
    }

    public function with(PermissionService $permissions, TicketService $tickets): array
    {
        $user = auth()->user();
        $employee = $user->employee;
        $scope = $permissions->scopeFor($user, 'helpdesk_tickets');
        $canSeeAll = $user->isAdmin() || $scope === 'all';

        $query = Ticket::with(['category', 'raisedBy', 'assignedTo', 'comments.author']);

        if (! $canSeeAll) {
            $query->where('raised_by_id', $employee?->id);
        } else {
            if (! $user->isAdmin()) {
                $handledConfidentialIds = HelpdeskCategory::where('is_confidential', true)->get()
                    ->filter(fn (HelpdeskCategory $c) => $tickets->isHandler($c, $user))
                    ->pluck('id');

                $query->where(function ($q) use ($handledConfidentialIds) {
                    $q->whereHas('category', fn ($q2) => $q2->where('is_confidential', false))
                        ->orWhereIn('helpdesk_category_id', $handledConfidentialIds);
                });
            }

            if ($this->mineOnly) {
                $query->where('raised_by_id', $employee?->id);
            }
        }

        return [
            'ticketsList' => $query->latest()->get(),
            'categories' => HelpdeskCategory::where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::orderBy('last_name')->get(),
            'canSeeAll' => $canSeeAll,
            'me' => $employee,
            'user' => $user,
            'ticketService' => $tickets,
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        @if(! $creating)
            <div class="flex items-center justify-between gap-3">
                <button wire:click="$set('creating', true)" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Raise a ticket</button>
                @if($canSeeAll)
                    <label class="flex items-center gap-1.5 text-xs text-text">
                        <input type="checkbox" wire:model.live="mineOnly" class="h-3.5 w-3.5 accent-primary">
                        My tickets only
                    </label>
                @endif
            </div>
        @else
            <form wire:submit="createTicket" class="flex flex-col gap-3">
                <div class="flex flex-wrap gap-3">
                    <div style="flex:1;min-width:200px;">
                        <label class="mb-1.5 block text-xs font-semibold text-text">Category</label>
                        <select wire:model.live="categoryId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                            <option value="">— select —</option>
                            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->is_confidential ? ' (confidential)' : '' }}</option>@endforeach
                        </select>
                        @error('categoryId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div style="flex:2;min-width:240px;">
                        <label class="mb-1.5 block text-xs font-semibold text-text">Subject</label>
                        <input type="text" wire:model.live="subject" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        @error('subject') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Description</label>
                    <textarea wire:model.live="description" rows="3" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                    @error('description') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                @if($categoryId && optional($categories->firstWhere('id', $categoryId))->is_confidential)
                    <label class="flex items-center gap-1.5 text-xs text-text">
                        <input type="checkbox" wire:model.live="isAnonymous" class="h-3.5 w-3.5 accent-primary">
                        Submit anonymously — your identity is hidden from handlers unless you're specifically identified for follow-up
                    </label>
                @endif
                <div class="flex gap-2">
                    <button type="submit" class="self-start rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Submit</button>
                    <button type="button" wire:click="$set('creating', false)" class="text-sm font-semibold text-text-muted">Cancel</button>
                </div>
            </form>
        @endif
    </section>

    <div class="flex flex-col gap-3">
        @foreach($ticketsList as $ticket)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button wire:click="$set('expandedId', {{ $expandedId === $ticket->id ? 'null' : $ticket->id }})" class="text-left">
                        <div class="font-display text-sm font-bold text-text">{{ $ticket->subject }}</div>
                        <div class="text-xs text-text-muted">{{ $ticket->category->name }} · raised by {{ $revealedNames[$ticket->id] ?? $ticket->raiserLabel() }} · {{ $ticket->created_at->format('j M Y') }}</div>
                    </button>
                    <span class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $ticket->status === 'open' ? 'bg-info-light text-info' : ($ticket->status === 'in_progress' ? 'bg-warning-light text-warning' : ($ticket->status === 'resolved' ? 'bg-accent-light text-accent' : 'bg-text-faint/15 text-text-muted')) }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                </div>

                @if($expandedId === $ticket->id)
                    <div class="mt-3 flex flex-col gap-3 border-t border-border pt-3">
                        <div class="text-sm text-text">{{ $ticket->description }}</div>

                        @if($ticket->assignedTo)
                            <div class="text-xs text-text-muted">Assigned to {{ $ticket->assignedTo->fullName() }}</div>
                        @endif

                        @if($ticketService->canManageStatus($ticket, $user))
                            <div class="flex flex-wrap items-center gap-2">
                                @if($ticket->status === 'open')
                                    <button wire:click="startProgress({{ $ticket->id }})" class="text-xs font-semibold text-primary">Start progress</button>
                                @endif
                                @if(in_array($ticket->status, ['open', 'in_progress']))
                                    <button wire:click="resolve({{ $ticket->id }})" class="text-xs font-semibold text-primary">Mark resolved</button>
                                @endif
                                @if($ticket->status === 'resolved')
                                    <button wire:click="close({{ $ticket->id }})" class="text-xs font-semibold text-primary">Close</button>
                                @endif
                                @if(in_array($ticket->status, ['resolved', 'closed']))
                                    <button wire:click="reopen({{ $ticket->id }})" class="text-xs font-semibold text-warning">Reopen</button>
                                @endif
                                @if($ticket->is_anonymous && ! isset($revealedNames[$ticket->id]))
                                    <button wire:click="revealIdentity({{ $ticket->id }})" wire:confirm="Revealing identity is logged to the audit trail. Continue?" class="text-xs font-semibold text-danger">Reveal identity</button>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <select wire:model="assignEmployeeId" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                    <option value="">— assign to —</option>
                                    @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                                </select>
                                <button wire:click="assign({{ $ticket->id }})" class="text-xs font-semibold text-primary">Assign</button>
                            </div>
                        @endif

                        <div>
                            <div class="mb-1.5 text-xs font-semibold text-text">Comments</div>
                            @forelse($ticket->comments as $comment)
                                <div class="mb-1.5 text-xs text-text-muted"><span class="font-semibold text-text">{{ $comment->author->fullName() }}:</span> {{ $comment->body }}</div>
                            @empty
                                <div class="text-xs text-text-muted">No comments yet.</div>
                            @endforelse
                            <div class="mt-2 flex gap-2">
                                <input type="text" wire:model="newComment.{{ $ticket->id }}" placeholder="Write a comment…" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                <button wire:click="addComment({{ $ticket->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Send</button>
                            </div>
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($ticketsList->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No tickets to show.</div>
        @endif
    </div>
</div>
