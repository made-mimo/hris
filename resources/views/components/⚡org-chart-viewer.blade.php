<?php

use App\Models\Employee;
use Livewire\Component;

/**
 * Spec B2: "Interactive, multi-root org chart (computed from the reporting-
 * line graph at read time, with cycle protection so a bad edit can never
 * produce an infinite loop)." Built from `supervisor_id` (the primary/
 * direct line) rather than the full multi-supervisor graph — a tree
 * visualization needs exactly one parent per node, so dotted-line/matrix
 * relationships (visible on each employee's own Reporting tab) aren't drawn
 * here. "Interactive" here means expand/collapse per node, not drag-to-
 * reassign — see PLAN.md for what a fuller build would add.
 */
new class extends Component
{
    public array $collapsed = [];

    public function toggle(int $id): void
    {
        if (in_array($id, $this->collapsed, true)) {
            $this->collapsed = array_values(array_diff($this->collapsed, [$id]));
        } else {
            $this->collapsed[] = $id;
        }
    }

    /** Flat, depth-annotated list — cycle-safe via $visited even though the Reporting tab's write-time guard should make a cycle unreachable in practice. */
    protected function buildRows(): array
    {
        $all = Employee::with(['jobTitle'])->orderBy('first_name')->get();
        $byManager = $all->groupBy('supervisor_id');
        $visited = [];
        $rows = [];

        $walk = function ($managerId, $depth) use (&$walk, &$rows, $byManager, &$visited) {
            foreach ($byManager->get($managerId, []) as $node) {
                if (in_array($node->id, $visited, true)) {
                    continue;
                }
                $visited[] = $node->id;
                $rows[] = ['node' => $node, 'depth' => $depth, 'hasChildren' => $byManager->has($node->id)];

                if (! in_array($node->id, $this->collapsed, true)) {
                    $walk($node->id, $depth + 1);
                }
            }
        };
        $walk(null, 0);

        return $rows;
    }

    public function with(): array
    {
        return ['rows' => $this->buildRows()];
    }
};
?>

<div class="rounded-md border border-border bg-surface p-5 shadow-sm">
    <div class="flex flex-col">
        @foreach($rows as $row)
            @php($node = $row['node'])
            <div class="flex items-center gap-2 border-b border-border py-2.5 last:border-0" style="padding-left: {{ $row['depth'] * 28 }}px;">
                @if($row['hasChildren'])
                    <button wire:click="toggle({{ $node->id }})" class="w-4 shrink-0 text-xs text-text-muted">{{ in_array($node->id, $collapsed, true) ? '▶' : '▼' }}</button>
                @else
                    <span class="w-4 shrink-0"></span>
                @endif
                <a href="{{ route('employees.show', $node) }}" wire:navigate class="text-sm font-semibold text-text hover:text-primary">{{ $node->fullName() }}</a>
                <span class="text-xs text-text-muted">{{ $node->jobTitleName() ?? '—' }}</span>
            </div>
        @endforeach
        @if(empty($rows))
            <div class="py-6 text-center text-sm text-text-muted">No employees yet.</div>
        @endif
    </div>
</div>
