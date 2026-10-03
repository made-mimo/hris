<?php

use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        return ['me' => auth()->user()->employee];
    }
};
?>

<x-layouts.app title="My Info">
    <div class="page-header">
        <div>
            <h1>My Info</h1>
            <p class="text-muted">{{ $me->fullName() }} · {{ $me->employee_id }}</p>
        </div>
    </div>

    <div class="grid grid-2" style="align-items:start;">
        <livewire:profile-photo />

        <section class="card">
            <div class="card-header"><h2>Details</h2></div>
            <div style="display:flex;flex-direction:column;gap:10px;font-size:var(--fs-sm);">
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Employee ID</span><span class="font-mono" style="font-weight:600;">{{ $me->employee_id }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Job title</span><span style="font-weight:600;">{{ $me->jobTitleName() }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Department</span><span style="font-weight:600;">{{ $me->departmentName() }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Supervisor</span><span style="font-weight:600;">{{ $me->supervisor?->fullName() ?? '—' }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Hire date</span><span style="font-weight:600;">{{ $me->hire_date->format(\App\Support\Dates::DATE) }}</span></div>
            </div>
        </section>
    </div>

    {{--
        Spec B2 / backlog #10: "Self-service 'My Info' view exposing the
        subset of the profile an employee may edit themselves." Reuses the
        same tab components the Admin/HR-facing employee profile editor
        uses — each already takes a plain Employee with no admin-only
        gating, so there's nothing self-service-specific to duplicate here.
        Onboarding/offboarding tasks are deliberately excluded: that's an
        HR-initiated workflow (employee-career-tab), not something an
        employee should be able to assign to themselves.
    --}}
    <div class="mt-6">
        <h2 class="mb-3" style="font-size:16px;font-weight:700;">Biodata</h2>
        <div class="flex flex-col gap-4">
            <livewire:employee-personal-tab :employee="$me" :key="'self-personal-'.$me->id" />
            <livewire:employee-contact-tab :employee="$me" :key="'self-contact-'.$me->id" />
            <livewire:employee-qualifications-tab :employee="$me" :key="'self-qualifications-'.$me->id" />
            <livewire:employee-development-tab :employee="$me" :key="'self-development-'.$me->id" />
        </div>
    </div>
</x-layouts.app>
