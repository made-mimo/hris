<?php

use App\Models\Employee;
use App\Models\RenewalNotifyTarget;
use App\Models\RenewalReminderTier;
use App\Models\RenewalType;
use App\Models\Role;
use Livewire\Component;

/**
 * Spec Section 3.2's Renewal & Compliance Reminder Engine: "an
 * Admin-configurable set of reminder tiers...and a notify list by role."
 * RenewalType/Tier/NotifyTarget were seeded once (60/30/14/7 days,
 * Admin+HR Admin) with no screen to change them afterwards — a documented
 * gap, closed here. No new schema: every model this screen manages already
 * existed for RenewalReminderEngine's own sweep() to read.
 *
 * Each renewal type renders its own repeated sub-form on this one page, so
 * every field is keyed by type id (`$daysBeforeExpiry[$typeId]`, etc.)
 * rather than one shared property reused across sections. `targetKind` is
 * `.live` (it conditionally swaps the role/employee select), so — per the
 * lesson learned the hard way in Phase 4 — the whole notify-target form is
 * built `.live` rather than mixing it with deferred fields.
 */
new class extends Component
{
    public array $daysBeforeExpiry = [];

    public array $targetKind = [];

    public array $roleId = [];

    public array $employeeId = [];

    public function addTier(int $typeId): void
    {
        $days = $this->daysBeforeExpiry[$typeId] ?? null;

        $this->validate([
            'daysBeforeExpiry.'.$typeId => ['required', 'integer', 'min:1', 'max:730'],
        ]);

        $exists = RenewalReminderTier::where('renewal_type_id', $typeId)
            ->where('days_before_expiry', $days)
            ->exists();

        abort_if($exists, 422, 'That tier already exists for this renewal type.');

        RenewalReminderTier::create(['renewal_type_id' => $typeId, 'days_before_expiry' => $days]);

        unset($this->daysBeforeExpiry[$typeId]);
        session()->flash('status', 'Reminder tier added.');
    }

    /** Tier ids are only ever referenced by number inside a Renewable's own fired_tier_ids array (never a foreign key on that table) — deleting one here simply stops it firing again; it's never re-matched against a stale id. */
    public function removeTier(int $id): void
    {
        RenewalReminderTier::findOrFail($id)->delete();
        session()->flash('status', 'Reminder tier removed.');
    }

    public function addNotifyTarget(int $typeId): void
    {
        $kind = $this->targetKind[$typeId] ?? 'role';

        $this->validate([
            'roleId.'.$typeId => [$kind === 'role' ? 'required' : 'nullable', 'exists:roles,id'],
            'employeeId.'.$typeId => [$kind === 'employee' ? 'required' : 'nullable', 'exists:employees,id'],
        ]);

        RenewalNotifyTarget::create([
            'renewal_type_id' => $typeId,
            'role_id' => $kind === 'role' ? $this->roleId[$typeId] : null,
            'user_id' => $kind === 'employee' ? Employee::find($this->employeeId[$typeId])?->user_id : null,
        ]);

        unset($this->roleId[$typeId], $this->employeeId[$typeId]);
        session()->flash('status', 'Notify target added.');
    }

    public function removeNotifyTarget(int $id): void
    {
        RenewalNotifyTarget::findOrFail($id)->delete();
        session()->flash('status', 'Notify target removed.');
    }

    public function with(): array
    {
        return [
            'types' => RenewalType::with(['tiers' => fn ($q) => $q->orderByDesc('days_before_expiry'), 'notifyTargets.role', 'notifyTargets.user'])->orderBy('label')->get(),
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

    @foreach($types as $type)
        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <h2 class="mb-3.5 font-display text-base font-bold text-text">{{ $type->label }}</h2>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Reminder tiers (days before expiry)</div>
                    <div class="mb-3 flex flex-wrap gap-2">
                        @forelse($type->tiers as $tier)
                            <span class="inline-flex items-center gap-1.5 rounded-pill bg-bg px-3 py-1.5 text-xs font-semibold text-text">
                                {{ $tier->days_before_expiry }} days
                                <button type="button" wire:click="removeTier({{ $tier->id }})" wire:confirm="Remove this reminder tier?" class="text-text-muted hover:text-danger">&times;</button>
                            </span>
                        @empty
                            <span class="text-xs text-text-muted">No tiers configured — this type will never fire a reminder.</span>
                        @endforelse
                    </div>
                    <form wire:submit="addTier({{ $type->id }})" class="flex flex-wrap items-end gap-2">
                        <input type="number" wire:model="daysBeforeExpiry.{{ $type->id }}" min="1" max="730" placeholder="e.g. 30" class="w-28 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add tier</button>
                    </form>
                    @error('daysBeforeExpiry.'.$type->id) <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>

                <div>
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Notify targets</div>
                    <div class="mb-3 flex flex-col gap-1.5">
                        @forelse($type->notifyTargets as $target)
                            <div class="flex items-center justify-between rounded-sm bg-bg px-3 py-1.5 text-xs">
                                <span>{{ $target->role ? $target->role->name.' (role)' : ($target->user?->name ?? 'Unknown user') }}</span>
                                <button type="button" wire:click="removeNotifyTarget({{ $target->id }})" wire:confirm="Remove this notify target?" class="font-semibold text-text-muted hover:text-danger">Remove</button>
                            </div>
                        @empty
                            <span class="text-xs text-text-muted">Nobody is notified for this type.</span>
                        @endforelse
                    </div>
                    <form wire:submit="addNotifyTarget({{ $type->id }})" class="flex flex-col gap-2">
                        <div class="flex gap-3 text-xs">
                            <label class="flex items-center gap-1"><input type="radio" wire:model.live="targetKind.{{ $type->id }}" value="role"> Role</label>
                            <label class="flex items-center gap-1"><input type="radio" wire:model.live="targetKind.{{ $type->id }}" value="employee"> Employee</label>
                        </div>
                        @if(($targetKind[$type->id] ?? 'role') === 'role')
                            <select wire:model.live="roleId.{{ $type->id }}" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                <option value="">— select role —</option>
                                @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                            </select>
                        @else
                            <select wire:model.live="employeeId.{{ $type->id }}" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                                <option value="">— select employee —</option>
                                @foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->fullName() }}</option>@endforeach
                            </select>
                        @endif
                        <button type="submit" class="self-start rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add target</button>
                    </form>
                </div>
            </div>
        </section>
    @endforeach
</div>
