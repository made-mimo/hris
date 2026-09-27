<?php

use App\Services\PermissionService;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        $scope = app(PermissionService::class)->scopeFor(auth()->user(), 'leave_requests');

        return ['canAssign' => in_array($scope, ['all', 'self_subordinates'], true)];
    }
};
?>

<x-layouts.app title="Approvals">
    <div class="page-header">
        <div>
            <h1>Approvals</h1>
            <p class="text-muted">Everything waiting on HR &amp; Admin, oldest first.</p>
        </div>
        @if($canAssign)
            <a href="{{ route('leave.assign') }}" wire:navigate class="btn btn-outline">Assign leave</a>
        @endif
    </div>

    <livewire:approvals-board />
</x-layouts.app>
