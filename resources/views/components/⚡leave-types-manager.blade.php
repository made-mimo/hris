<?php

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Str;
use Livewire\Component;

/** Spec C1: leave type configuration, including minimumTenureMonths (the new-hire waiting period) and carriesOverAtYearEnd + its cap. Deletion is deliberately never blocked (unlike every other master-data type in this app) — spec explicitly wants it to proceed and move any still-open request into a restricted, cancel-only state instead. */
new class extends Component
{
    public string $name = '';

    public int $minimumTenureMonths = 0;

    public float $standardAnnualDays = 0;

    public bool $carriesOverAtYearEnd = false;

    public ?float $carryoverCapDays = null;

    public bool $excludeFromReports = false;

    public ?int $editingId = null;

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100', 'unique:leave_types,name'],
            'minimumTenureMonths' => ['required', 'integer', 'min:0', 'max:120'],
            'standardAnnualDays' => ['required', 'numeric', 'min:0'],
            'carryoverCapDays' => ['nullable', 'numeric', 'min:0'],
        ]);

        LeaveType::create([
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'minimum_tenure_months' => $this->minimumTenureMonths,
            'standard_annual_days' => $this->standardAnnualDays,
            'carries_over_at_year_end' => $this->carriesOverAtYearEnd,
            'carryover_cap_days' => $this->carriesOverAtYearEnd ? $this->carryoverCapDays : null,
            'exclude_from_reports_if_unentitled' => $this->excludeFromReports,
            'sort_order' => LeaveType::max('sort_order') + 1,
        ]);

        $this->reset('name', 'minimumTenureMonths', 'standardAnnualDays', 'carriesOverAtYearEnd', 'carryoverCapDays', 'excludeFromReports');
        session()->flash('status', 'Leave type added.');
    }

    public function startEdit(int $id): void
    {
        $type = LeaveType::findOrFail($id);
        $this->editingId = $id;
        $this->name = $type->name;
        $this->minimumTenureMonths = $type->minimum_tenure_months;
        $this->standardAnnualDays = (float) $type->standard_annual_days;
        $this->carriesOverAtYearEnd = $type->carries_over_at_year_end;
        $this->carryoverCapDays = $type->carryover_cap_days;
        $this->excludeFromReports = $type->exclude_from_reports_if_unentitled;
    }

    public function update(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100', 'unique:leave_types,name,'.$this->editingId],
            'minimumTenureMonths' => ['required', 'integer', 'min:0', 'max:120'],
            'standardAnnualDays' => ['required', 'numeric', 'min:0'],
            'carryoverCapDays' => ['nullable', 'numeric', 'min:0'],
        ]);

        LeaveType::findOrFail($this->editingId)->update([
            'name' => $this->name,
            'minimum_tenure_months' => $this->minimumTenureMonths,
            'standard_annual_days' => $this->standardAnnualDays,
            'carries_over_at_year_end' => $this->carriesOverAtYearEnd,
            'carryover_cap_days' => $this->carriesOverAtYearEnd ? $this->carryoverCapDays : null,
            'exclude_from_reports_if_unentitled' => $this->excludeFromReports,
        ]);

        $this->cancelEdit();
        session()->flash('status', 'Leave type updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset('name', 'minimumTenureMonths', 'standardAnnualDays', 'carriesOverAtYearEnd', 'carryoverCapDays', 'excludeFromReports');
    }

    /** Spec C1: "if a leave type is deleted while requests against it are still open, those requests move into a restricted state where the only remaining allowed action is cancellation." Deletion itself is never blocked. */
    public function delete(int $id): void
    {
        $type = LeaveType::findOrFail($id);

        $openCount = LeaveRequest::where('leave_type_id', $id)
            ->whereIn('status', ['pending_manager', 'pending_hr'])
            ->update(['status' => 'restricted']);

        $type->delete();

        session()->flash('status', $openCount > 0
            ? "\"{$type->name}\" deleted — {$openCount} open request(s) moved to a restricted, cancel-only state."
            : "\"{$type->name}\" deleted.");
    }

    public function with(): array
    {
        return ['types' => LeaveType::withCount('leaveRequests')->orderBy('sort_order')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="{{ $editingId ? 'update' : 'create' }}" class="mb-4 flex flex-col gap-3">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Name</label>
                    <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Min. tenure (months)</label>
                    <input type="number" wire:model="minimumTenureMonths" min="0" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Standard annual days</label>
                    <input type="number" step="0.5" wire:model="standardAnnualDays" min="0" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-xs font-semibold text-text">
                        <input type="checkbox" wire:model.live="carriesOverAtYearEnd" class="h-4 w-4 accent-primary"> Carries over at year end
                    </label>
                </div>
                @if($carriesOverAtYearEnd)
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-text">Carryover cap (days)</label>
                        <input type="number" step="0.5" wire:model="carryoverCapDays" placeholder="e.g. 10" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    </div>
                @endif
            </div>
            <label class="flex items-center gap-2 text-xs font-semibold text-text">
                <input type="checkbox" wire:model="excludeFromReports" class="h-4 w-4 accent-primary"> Exclude from reports if unentitled
            </label>
            @error('name') <div class="text-xs text-danger">{{ $message }}</div> @enderror
            @error('minimumTenureMonths') <div class="text-xs text-danger">{{ $message }}</div> @enderror
            @error('carryoverCapDays') <div class="text-xs text-danger">{{ $message }}</div> @enderror
            <div class="flex gap-2">
                <button type="submit" class="self-start rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">{{ $editingId ? 'Save changes' : 'Add leave type' }}</button>
                @if($editingId)
                    <button type="button" wire:click="cancelEdit" class="self-start rounded-sm border border-border px-4 py-2 text-sm font-semibold text-text-muted">Cancel</button>
                @endif
            </div>
        </form>

        <div class="divide-y divide-border">
            @foreach($types as $type)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="font-medium text-text">{{ $type->name }}</span>
                        <span class="text-xs text-text-muted">{{ $type->standard_annual_days }}d/yr</span>
                        @if($type->minimum_tenure_months > 0) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ $type->minimum_tenure_months }}mo wait</span> @endif
                        @if($type->carries_over_at_year_end) <span class="rounded-pill bg-accent-light px-2 py-0.5 text-[10px] font-semibold text-accent">Carries over{{ $type->carryover_cap_days ? ' (cap '.$type->carryover_cap_days.'d)' : '' }}</span> @endif
                        <span class="text-xs text-text-faint">{{ $type->leave_requests_count }} request{{ $type->leave_requests_count === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="startEdit({{ $type->id }})" class="text-xs font-semibold text-primary">Edit</button>
                        <button wire:click="delete({{ $type->id }})" wire:confirm="Delete this leave type? Any open requests against it will move to a cancel-only state, not be deleted." class="text-xs font-semibold text-danger">Delete</button>
                    </div>
                </div>
            @endforeach
            @if($types->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No leave types yet.</div>
            @endif
        </div>
    </section>
</div>
