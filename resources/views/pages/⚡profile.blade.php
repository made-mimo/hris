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
            <div class="hint" style="margin-top:14px;">Full Employee Master Record editing (spec B2) isn't part of this prototype slice.</div>
        </section>
    </div>

    <div class="grid grid-2" style="align-items:start;margin-top:24px;">
        <section class="card">
            <div class="card-header"><h2>Change password</h2></div>
            <livewire:change-password />
        </section>

        <livewire:account-security />
    </div>
</x-layouts.app>
