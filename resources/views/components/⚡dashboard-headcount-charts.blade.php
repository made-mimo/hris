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
        ];
    }
};
?>

<div class="grid grid-2" style="gap:18px;">
    <section class="card">
        <div class="card-header">
            <h2>Headcount by department</h2>
        </div>
        @if($byDepartment->isNotEmpty())
            <x-chart-canvas
                id="dashboard-headcount-department"
                type="doughnut"
                :labels="$byDepartment->keys()->all()"
                :datasets="[['data' => $byDepartment->values()->all(), 'backgroundColor' => ['#D9251E', '#1E9E63', '#2563EB', '#B45309', '#7C3AED', '#DB2777', '#0891B2', '#65A30D']]]"
                :height="240"
            />
        @else
            <p class="text-muted">No active employees yet.</p>
        @endif
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Headcount by location</h2>
        </div>
        @if($byLocation->isNotEmpty())
            <x-chart-canvas
                id="dashboard-headcount-location"
                type="doughnut"
                :labels="$byLocation->keys()->all()"
                :datasets="[['data' => $byLocation->values()->all(), 'backgroundColor' => ['#1E9E63', '#D9251E', '#2563EB', '#B45309', '#7C3AED', '#DB2777', '#0891B2', '#65A30D']]]"
                :height="240"
            />
        @else
            <p class="text-muted">No active employees yet.</p>
        @endif
    </section>
</div>
