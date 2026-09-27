<?php

use App\Models\PayGrade;
use App\Models\PayGradeBand;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public ?int $editingId = null;

    public string $editName = '';

    public bool $editActive = true;

    public ?int $addingBandFor = null;

    public string $bandCurrency = '';

    public string $bandMin = '';

    public string $bandMax = '';

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:150', 'unique:pay_grades,name']]);

        PayGrade::create(['name' => $this->name]);
        $this->reset('name');
        session()->flash('status', 'Pay grade added.');
    }

    public function startEdit(int $id): void
    {
        $grade = PayGrade::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $grade->name;
        $this->editActive = $grade->is_active;
    }

    public function update(): void
    {
        $this->validate(['editName' => ['required', 'string', 'max:150', 'unique:pay_grades,name,'.$this->editingId]]);

        PayGrade::findOrFail($this->editingId)->update(['name' => $this->editName, 'is_active' => $this->editActive]);
        $this->editingId = null;
        session()->flash('status', 'Pay grade updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        PayGrade::findOrFail($id)->delete();
        session()->flash('status', 'Pay grade deleted.');
    }

    public function startAddBand(int $gradeId): void
    {
        $this->addingBandFor = $gradeId;
        $this->reset('bandCurrency', 'bandMin', 'bandMax');
    }

    public function cancelAddBand(): void
    {
        $this->addingBandFor = null;
    }

    public function addBand(): void
    {
        $this->validate([
            'bandCurrency' => ['required', 'string', 'size:3', 'uppercase', 'unique:pay_grade_bands,currency,NULL,id,pay_grade_id,'.$this->addingBandFor],
            'bandMin' => ['required', 'numeric', 'min:0'],
            'bandMax' => ['required', 'numeric', 'gt:bandMin'],
        ]);

        PayGradeBand::create([
            'pay_grade_id' => $this->addingBandFor,
            'currency' => strtoupper($this->bandCurrency),
            'min_salary' => $this->bandMin,
            'max_salary' => $this->bandMax,
        ]);
        $this->addingBandFor = null;
        session()->flash('status', 'Currency band added.');
    }

    public function deleteBand(int $bandId): void
    {
        PayGradeBand::findOrFail($bandId)->delete();
        session()->flash('status', 'Currency band removed.');
    }

    public function with(): array
    {
        return ['grades' => PayGrade::with('bands')->orderBy('name')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <form wire:submit="create" class="mb-4 flex items-end gap-3">
            <div class="flex-1">
                <label for="name" class="mb-1.5 block text-xs font-semibold text-text">New pay grade</label>
                <input id="name" type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark">Add</button>
        </form>

        <div class="flex flex-col gap-3">
            @foreach($grades as $grade)
                <div class="rounded-sm border border-border p-3.5">
                    <div class="flex items-center justify-between gap-3">
                        @if($editingId === $grade->id)
                            <div class="flex flex-1 items-center gap-3">
                                <input type="text" wire:model="editName" class="flex-1 rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-text outline-none focus:border-primary">
                                <label class="flex items-center gap-1.5 text-xs text-text">
                                    <input type="checkbox" wire:model="editActive" class="h-4 w-4 accent-primary"> Active
                                </label>
                                <button wire:click="update" class="text-xs font-semibold text-primary">Save</button>
                                <button wire:click="cancelEdit" class="text-xs font-semibold text-text-muted">Cancel</button>
                            </div>
                        @else
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-semibold text-text">{{ $grade->name }}</span>
                                @if(! $grade->is_active) <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">Inactive</span> @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <button wire:click="startEdit({{ $grade->id }})" class="text-xs font-semibold text-primary">Edit</button>
                                <button wire:click="delete({{ $grade->id }})" wire:confirm="Delete this pay grade and all its currency bands?" class="text-xs font-semibold text-danger">Delete</button>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-col gap-1.5 border-t border-border pt-3">
                        @foreach($grade->bands as $band)
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-text">{{ $band->currency }} {{ number_format($band->min_salary, 2) }} – {{ number_format($band->max_salary, 2) }}</span>
                                <button wire:click="deleteBand({{ $band->id }})" wire:confirm="Remove this currency band?" class="font-semibold text-danger">Remove</button>
                            </div>
                        @endforeach
                        @if($grade->bands->isEmpty() && $addingBandFor !== $grade->id)
                            <div class="text-xs text-text-muted">No currency bands yet.</div>
                        @endif

                        @if($addingBandFor === $grade->id)
                            <div class="mt-2 grid grid-cols-3 gap-2 md:grid-cols-4">
                                <input type="text" wire:model="bandCurrency" maxlength="3" placeholder="NGN" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs uppercase text-text outline-none focus:border-primary">
                                <input type="number" step="0.01" wire:model="bandMin" placeholder="Min" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                <input type="number" step="0.01" wire:model="bandMax" placeholder="Max" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                <div class="flex items-center gap-2">
                                    <button wire:click="addBand" class="text-xs font-semibold text-primary">Save</button>
                                    <button wire:click="cancelAddBand" class="text-xs font-semibold text-text-muted">Cancel</button>
                                </div>
                            </div>
                            @error('bandCurrency') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                            @error('bandMin') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                            @error('bandMax') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                        @else
                            <button wire:click="startAddBand({{ $grade->id }})" class="mt-1 self-start text-xs font-semibold text-primary">+ Add currency band</button>
                        @endif
                    </div>
                </div>
            @endforeach
            @if($grades->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No pay grades yet.</div>
            @endif
        </div>
    </section>
</div>
