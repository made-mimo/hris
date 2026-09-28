<?php

use App\Models\Employee;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskCategoryHandler;
use App\Models\Role;
use App\Services\TicketService;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public bool $isConfidential = false;

    public ?int $grantCategoryId = null;

    public string $grantType = 'role';

    public ?int $roleId = null;

    public ?int $employeeId = null;

    public function create(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:100', 'unique:helpdesk_categories,name']]);

        HelpdeskCategory::create(['name' => $data['name'], 'is_confidential' => $this->isConfidential]);

        $this->reset('name', 'isConfidential');
        session()->flash('status', 'Category added.');
    }

    public function toggleActive(int $id): void
    {
        $category = HelpdeskCategory::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function grantHandler(TicketService $tickets): void
    {
        $data = $this->validate([
            'grantCategoryId' => ['required', 'exists:helpdesk_categories,id'],
            'roleId' => ['required_if:grantType,role', 'nullable', 'exists:roles,id'],
            'employeeId' => ['required_if:grantType,employee', 'nullable', 'exists:employees,id'],
        ]);

        $tickets->grantHandler(
            $data['grantCategoryId'],
            $this->grantType === 'role' ? $data['roleId'] : null,
            $this->grantType === 'employee' ? $data['employeeId'] : null,
        );

        $this->reset('roleId', 'employeeId');
        session()->flash('status', 'Handler added.');
    }

    public function revokeHandler(int $id): void
    {
        HelpdeskCategoryHandler::findOrFail($id)->delete();
        session()->flash('status', 'Handler removed.');
    }

    public function with(): array
    {
        return [
            'categories' => HelpdeskCategory::withCount('tickets')->orderBy('name')->get(),
            'confidentialCategories' => HelpdeskCategory::where('is_confidential', true)->orderBy('name')->get(),
            'handlers' => HelpdeskCategoryHandler::with(['category', 'role', 'employee'])->latest()->get(),
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

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New category</h2>
        <form wire:submit="create" class="flex flex-wrap items-end gap-3">
            <div style="flex:1;min-width:200px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Name</label>
                <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <label class="flex items-center gap-1.5 pb-2.5 text-xs text-text">
                <input type="checkbox" wire:model="isConfidential" class="h-3.5 w-3.5 accent-primary">
                Confidential (restricted to a handler list, not all HR)
            </label>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Tickets</th>
                    <th class="px-4 py-2.5">Confidential</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $category)
                    <tr class="border-b border-border last:border-0">
                        <td class="px-4 py-2.5 text-text">{{ $category->name }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $category->tickets_count }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $category->is_confidential ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-2.5">
                            <button wire:click="toggleActive({{ $category->id }})" class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $category->is_active ? 'bg-accent-light text-accent' : 'bg-text-faint/15 text-text-muted' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Confidential category handlers</h2>
        @if($confidentialCategories->isEmpty())
            <div class="text-sm text-text-muted">No confidential categories yet.</div>
        @else
            <form wire:submit="grantHandler" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Category</label>
                    <select wire:model.live="grantCategoryId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($confidentialCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('grantCategoryId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
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

            <div class="mt-3.5 flex flex-col gap-1.5">
                @foreach($handlers as $handler)
                    <div class="flex items-center justify-between rounded-sm border border-border bg-bg px-3 py-2 text-xs">
                        <span>{{ $handler->category->name }} — {{ $handler->role ? 'Role: '.$handler->role->name : 'Employee: '.$handler->employee?->fullName() }}</span>
                        <button wire:click="revokeHandler({{ $handler->id }})" wire:confirm="Revoke this handler grant?" class="font-semibold text-danger">Revoke</button>
                    </div>
                @endforeach
                @if($handlers->isEmpty())
                    <div class="text-xs text-text-muted">No handler grants yet.</div>
                @endif
            </div>
        @endif
    </section>
</div>
