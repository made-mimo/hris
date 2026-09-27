<?php

use App\Models\MasterListItem;
use App\Models\Province;
use Livewire\Component;

new class extends Component
{
    public ?int $countryId = null;

    public string $name = '';

    public function create(): void
    {
        $this->validate([
            'countryId' => ['required', 'exists:master_list_items,id'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        Province::create(['country_id' => $this->countryId, 'name' => $this->name]);
        $this->reset('name');
        session()->flash('status', 'Province added.');
    }

    public function delete(int $id): void
    {
        Province::findOrFail($id)->delete();
        session()->flash('status', 'Province deleted.');
    }

    public function with(): array
    {
        return [
            'countries' => MasterListItem::ofType(MasterListItem::TYPE_COUNTRY)->where('is_active', true)->orderBy('name')->get(),
            'provinces' => Province::with('country')->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        @if($countries->isEmpty())
            <div class="mb-3 text-xs text-text-muted">Add at least one Country under Other Lists before adding provinces.</div>
        @endif

        <form wire:submit="create" class="mb-4 flex items-end gap-3">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Country</label>
                <select wire:model="countryId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">— select —</option>
                    @foreach($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="flex-1">
                <label class="mb-1.5 block text-xs font-semibold text-text">Province</label>
                <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('countryId') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        @error('name') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror

        <div class="divide-y divide-border">
            @foreach($provinces as $p)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <span>{{ $p->name }} <span class="text-text-muted">— {{ $p->country->name }}</span></span>
                    <button wire:click="delete({{ $p->id }})" wire:confirm="Delete this province?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($provinces->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No provinces yet.</div>
            @endif
        </div>
    </section>
</div>
