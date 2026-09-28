<?php

use App\Models\Employee;
use App\Models\PolicyCategory;
use App\Models\PolicyFileAccessGrant;
use App\Models\Role;
use App\Services\PolicyService;
use Livewire\Component;

new class extends Component
{
    public ?int $policyCategoryId = null;

    public string $grantType = 'role';

    public ?int $roleId = null;

    public ?int $employeeId = null;

    public function grant(PolicyService $policy): void
    {
        $data = $this->validate([
            'policyCategoryId' => ['required', 'exists:policy_categories,id'],
            'roleId' => ['required_if:grantType,role', 'nullable', 'exists:roles,id'],
            'employeeId' => ['required_if:grantType,employee', 'nullable', 'exists:employees,id'],
        ]);

        $policy->grantAccess(
            $data['policyCategoryId'],
            $this->grantType === 'role' ? $data['roleId'] : null,
            $this->grantType === 'employee' ? $data['employeeId'] : null,
        );

        $this->reset('roleId', 'employeeId');
        session()->flash('status', 'Access granted.');
    }

    public function revoke(int $id): void
    {
        PolicyFileAccessGrant::findOrFail($id)->delete();
        session()->flash('status', 'Access grant revoked.');
    }

    public function with(): array
    {
        return [
            'restrictedCategories' => PolicyCategory::where('is_restricted', true)->orderBy('name')->get(),
            'grants' => PolicyFileAccessGrant::with(['category', 'role', 'employee'])->latest()->get(),
            'roles' => Role::orderBy('name')->get(),
            'employees' => Employee::orderBy('last_name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    @if($restrictedCategories->isEmpty())
        <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No restricted categories yet — mark a category restricted on the Categories tab first.</div>
    @else
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">Grant access</h2>
            <form wire:submit="grant" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Restricted category</label>
                    <select wire:model.live="policyCategoryId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($restrictedCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('policyCategoryId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="flex gap-3 pb-2.5 text-xs">
                    <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="grantType" value="role" class="accent-primary"> By role</label>
                    <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="grantType" value="employee" class="accent-primary"> By employee</label>
                </div>
                @if($grantType === 'role')
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Role</label>
                        <select wire:model.live="roleId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                            <option value="">— select —</option>
                            @foreach($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                        </select>
                        @error('roleId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                @else
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Employee</label>
                        <select wire:model.live="employeeId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                            <option value="">— select —</option>
                            @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                        </select>
                        @error('employeeId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                    </div>
                @endif
                <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Grant</button>
            </form>
        </section>
    @endif

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Category</th>
                    <th class="px-4 py-2.5">Granted to</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($grants as $grant)
                    <tr class="border-b border-border last:border-0">
                        <td class="px-4 py-2.5 text-text">{{ $grant->category->name }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $grant->role ? 'Role: '.$grant->role->name : 'Employee: '.$grant->employee?->fullName() }}</td>
                        <td class="px-4 py-2.5">
                            <button wire:click="revoke({{ $grant->id }})" wire:confirm="Revoke this access grant?" class="text-xs font-semibold text-danger">Revoke</button>
                        </td>
                    </tr>
                @endforeach
                @if($grants->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="3">No access grants yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
