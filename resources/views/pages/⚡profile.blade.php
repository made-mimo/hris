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

<x-layouts.app title="My Profile">
    <div class="page-header">
        <div>
            <h1>My Profile</h1>
            <p class="text-muted">{{ $me->fullName() }} · {{ $me->employee_id }}</p>
        </div>
    </div>

    <div class="grid grid-2" style="align-items:start;">
        <livewire:profile-photo />

        <section class="card">
            <div class="card-header"><h2>Details</h2></div>
            <div style="display:flex;flex-direction:column;gap:10px;font-size:13.5px;">
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Employee ID</span><span class="font-mono" style="font-weight:600;">{{ $me->employee_id }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Job title</span><span style="font-weight:600;">{{ $me->jobTitleName() }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Department</span><span style="font-weight:600;">{{ $me->departmentName() }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Supervisor</span><span style="font-weight:600;">{{ $me->supervisor?->fullName() ?? '—' }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Hire date</span><span style="font-weight:600;">{{ $me->hire_date->format('j M Y') }}</span></div>
            </div>
        </section>
    </div>

    {{--
        Spec B2: "Self-service 'My Info' view exposing the subset of the
        profile an employee may edit themselves." Reuses the same Personal/
        Contact tab components the Admin/HR-facing employee profile editor
        uses (employee-detail-form.blade.php's Job Details tab and beyond) —
        both already take a plain Employee, with no admin-only gating, so
        there's nothing self-service-specific to duplicate here.
    --}}
    <div class="mt-6">
        <h2 class="mb-3" style="font-size:16px;font-weight:700;">My Info</h2>
        <div class="flex flex-col gap-4">
            <livewire:employee-personal-tab :employee="$me" :key="'self-personal-'.$me->id" />
            <livewire:employee-contact-tab :employee="$me" :key="'self-contact-'.$me->id" />
        </div>
    </div>
</x-layouts.app>
