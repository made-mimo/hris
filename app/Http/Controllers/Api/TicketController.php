<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TicketResource;
use App\Models\HelpdeskCategory;
use App\Models\Ticket;
use App\Services\PermissionService;
use App\Services\TicketService;
use Illuminate\Http\Request;

/**
 * Spec F6: "helpdesk tickets." Every authorization decision here reuses
 * TicketService's own canView()/canManageStatus()/isHandler() methods — the
 * same gates ⚡ticket-manager.blade.php already enforces — rather than a
 * parallel API-only ruleset, so the confidential Grievance/Whistleblower
 * category (spec F4) is exactly as restricted here as on the web.
 */
class TicketController extends ApiController
{
    public function index(Request $request, PermissionService $permissions, TicketService $tickets)
    {
        $user = $request->user();
        $employee = $user->employee;
        $scope = $permissions->scopeFor($user, 'helpdesk_tickets');
        $canSeeAll = $user->isAdmin() || $scope === 'all';

        $query = Ticket::with(['category', 'raisedBy', 'assignedTo']);

        if (! $canSeeAll) {
            $query->where('raised_by_id', $employee?->id);
        } elseif (! $user->isAdmin()) {
            $handledConfidentialIds = HelpdeskCategory::where('is_confidential', true)->get()
                ->filter(fn (HelpdeskCategory $c) => $tickets->isHandler($c, $user))
                ->pluck('id');

            $query->where(function ($q) use ($handledConfidentialIds) {
                $q->whereHas('category', fn ($q2) => $q2->where('is_confidential', false))
                    ->orWhereIn('helpdesk_category_id', $handledConfidentialIds);
            });
        }

        if ($request->boolean('mine_only') && $canSeeAll) {
            $query->where('raised_by_id', $employee?->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $paginated = $query->latest()->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->success(
            TicketResource::collection($paginated->items()),
            ['page' => $paginated->currentPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()]
        );
    }

    public function show(Request $request, Ticket $ticket, TicketService $tickets)
    {
        abort_unless($tickets->canView($ticket, $request->user()), 403, 'You do not have access to this ticket.');

        return $this->success(new TicketResource($ticket->load(['category', 'raisedBy', 'assignedTo', 'comments.author'])));
    }

    public function store(Request $request, TicketService $tickets)
    {
        $data = $request->validate([
            'helpdesk_category_id' => ['required', 'exists:helpdesk_categories,id'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
            'is_anonymous' => ['boolean'],
        ]);

        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $category = HelpdeskCategory::findOrFail($data['helpdesk_category_id']);

        $ticket = $tickets->raise(
            $employee,
            $category,
            $data['subject'],
            $data['description'],
            $category->is_confidential && ($data['is_anonymous'] ?? false)
        );

        return $this->success(new TicketResource($ticket->load(['category', 'raisedBy'])), status: 201);
    }

    public function addComment(Request $request, Ticket $ticket, TicketService $tickets)
    {
        abort_unless($tickets->canView($ticket, $request->user()), 403, 'You do not have access to this ticket.');

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $tickets->addComment($ticket, $employee, $data['body']);

        return $this->success(new TicketResource($ticket->fresh(['category', 'raisedBy', 'assignedTo', 'comments.author'])), status: 201);
    }

    /** One endpoint for the whole status lifecycle (spec F4: Open → In Progress → Resolved/Closed, reopenable) — the `action` param picks the transition, TicketService enforces canManageStatus() either way. */
    public function updateStatus(Request $request, Ticket $ticket, TicketService $tickets)
    {
        abort_unless($tickets->canManageStatus($ticket, $request->user()), 403, 'You do not have access to manage this ticket.');

        $data = $request->validate(['action' => ['required', 'in:start_progress,resolve,close,reopen']]);

        match ($data['action']) {
            'start_progress' => $tickets->startProgress($ticket),
            'resolve' => $tickets->resolve($ticket),
            'close' => $tickets->close($ticket),
            'reopen' => $tickets->reopen($ticket),
        };

        return $this->success(new TicketResource($ticket->fresh(['category', 'raisedBy', 'assignedTo'])));
    }
}
