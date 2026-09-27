<?php

use App\Models\AuditLog;
use App\Models\Employee;
use Livewire\Component;

/** Spec B2: "employee activity log ... this can be unified with the general audit log rather than kept separate" — this tab is that unification: the same `audit_logs` rows the Auditable trait already writes on every Employee field change, filtered to this one record. */
new class extends Component
{
    public Employee $employee;

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function with(): array
    {
        return [
            'entries' => AuditLog::where('auditable_type', Employee::class)
                ->where('auditable_id', $this->employee->id)
                ->with('actor')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
        ];
    }
};
?>

<div>
    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Activity</h2>
        <div class="divide-y divide-border">
            @foreach($entries as $entry)
                <div class="py-2.5 text-sm">
                    <div class="flex items-center gap-2.5">
                        <span class="font-medium text-text">{{ ucfirst($entry->action) }}</span>
                        <span class="text-text-muted">by {{ $entry->actor_label ?? $entry->actor?->name ?? 'System' }}</span>
                        <span class="text-xs text-text-faint">{{ $entry->created_at->format('j M Y, g:ia') }}</span>
                    </div>
                    @if($entry->action === 'updated' && ! empty($entry->changes))
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text-muted">
                            @foreach($entry->changes as $field => $change)
                                <span><span class="font-semibold">{{ $field }}</span>: {{ $change[0] ?? '—' }} → {{ $change[1] ?? '—' }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
            @if($entries->isEmpty())
                <div class="py-4 text-center text-sm text-text-muted">No activity recorded yet.</div>
            @endif
        </div>
    </section>
</div>
