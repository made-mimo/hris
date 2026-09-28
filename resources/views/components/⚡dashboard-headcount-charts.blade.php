<?php

use App\Models\Employee;
use Livewire\Component;

/** Spec F2: "headcount-by-department and headcount-by-location charts." */
new class extends Component
{
    public function with(): array
    {
        $active = Employee::where('is_gdpr_purged', false)->whereDoesntHave('terminations')->with('subUnit', 'location')->get();

        $byDepartment = $active->groupBy(fn (Employee $e) => $e->subUnit?->name ?? 'Unassigned')
            ->map->count()->sortDesc();

        $byLocation = $active->groupBy(fn (Employee $e) => $e->location?->name ?? 'Unassigned')
            ->map->count()->sortDesc();

        return [
            'byDepartment' => $byDepartment,
            'byLocation' => $byLocation,
            'maxDepartment' => $byDepartment->max() ?: 1,
            'maxLocation' => $byLocation->max() ?: 1,
        ];
    }
};
?>

<div class="grid grid-2" style="gap:18px;">
    <section class="card">
        <div class="card-header">
            <h2>Headcount by department</h2>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            @forelse($byDepartment as $name => $count)
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                        <span>{{ $name }}</span><span class="font-mono" style="font-weight:600;">{{ $count }}</span>
                    </div>
                    <div style="height:8px;border-radius:4px;background:var(--color-bg);overflow:hidden;">
                        <div style="height:100%;border-radius:4px;background:var(--color-primary);width:{{ round($count / $maxDepartment * 100) }}%;"></div>
                    </div>
                </div>
            @empty
                <p class="text-muted">No active employees yet.</p>
            @endforelse
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Headcount by location</h2>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            @forelse($byLocation as $name => $count)
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                        <span>{{ $name }}</span><span class="font-mono" style="font-weight:600;">{{ $count }}</span>
                    </div>
                    <div style="height:8px;border-radius:4px;background:var(--color-bg);overflow:hidden;">
                        <div style="height:100%;border-radius:4px;background:var(--color-accent);width:{{ round($count / $maxLocation * 100) }}%;"></div>
                    </div>
                </div>
            @empty
                <p class="text-muted">No active employees yet.</p>
            @endforelse
        </div>
    </section>
</div>
