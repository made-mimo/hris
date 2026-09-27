<?php

use App\Models\OnboardingOffboardingTemplate;
use App\Models\OnboardingOffboardingTemplateItem;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $type = 'onboarding';

    public ?int $expandedId = null;

    public string $itemTitle = '';

    public string $itemOffsetDays = '0';

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:onboarding,offboarding'],
        ]);

        $template = OnboardingOffboardingTemplate::create(['name' => $this->name, 'type' => $this->type]);
        $this->reset('name');
        $this->expandedId = $template->id;
        session()->flash('status', 'Template created.');
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        $this->reset('itemTitle', 'itemOffsetDays');
    }

    public function addItem(): void
    {
        $this->validate([
            'itemTitle' => ['required', 'string', 'max:150'],
            'itemOffsetDays' => ['required', 'integer'],
        ]);

        OnboardingOffboardingTemplateItem::create([
            'template_id' => $this->expandedId,
            'title' => $this->itemTitle,
            'offset_days' => $this->itemOffsetDays,
            'sort_order' => OnboardingOffboardingTemplateItem::where('template_id', $this->expandedId)->max('sort_order') + 1,
        ]);

        $this->reset('itemTitle', 'itemOffsetDays');
        $this->itemOffsetDays = '0';
    }

    public function deleteItem(int $id): void
    {
        OnboardingOffboardingTemplateItem::findOrFail($id)->delete();
    }

    public function deleteTemplate(int $id): void
    {
        OnboardingOffboardingTemplate::findOrFail($id)->delete();
        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
        session()->flash('status', 'Template deleted.');
    }

    public function with(): array
    {
        return ['templates' => OnboardingOffboardingTemplate::with('items')->orderBy('type')->orderBy('name')->get()];
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
                <label class="mb-1.5 block text-xs font-semibold text-text">Template name</label>
                <input type="text" wire:model="name" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                @error('name') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Type</label>
                <select wire:model="type" class="rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary">
                    <option value="onboarding">Onboarding</option>
                    <option value="offboarding">Offboarding</option>
                </select>
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Create template</button>
        </form>

        <div class="flex flex-col gap-3">
            @foreach($templates as $template)
                <div class="rounded-sm border border-border p-3.5">
                    <div class="flex items-center justify-between">
                        <button wire:click="toggleExpand({{ $template->id }})" class="flex items-center gap-2.5 text-left">
                            <span class="text-sm font-semibold text-text">{{ $template->name }}</span>
                            <span class="rounded-pill bg-text-faint/15 px-2 py-0.5 text-[10px] font-semibold text-text-muted">{{ ucfirst($template->type) }}</span>
                            <span class="text-xs text-text-muted">{{ $template->items->count() }} item{{ $template->items->count() === 1 ? '' : 's' }}</span>
                        </button>
                        <button wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Delete this template and all its items?" class="text-xs font-semibold text-danger">Delete</button>
                    </div>

                    @if($expandedId === $template->id)
                        <div class="mt-3 flex flex-col gap-1.5 border-t border-border pt-3">
                            @foreach($template->items->sortBy('sort_order') as $item)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-text">{{ $item->title }} <span class="text-text-muted">({{ $item->offset_days >= 0 ? '+' : '' }}{{ $item->offset_days }} days)</span></span>
                                    <button wire:click="deleteItem({{ $item->id }})" class="font-semibold text-danger">Remove</button>
                                </div>
                            @endforeach

                            <div class="mt-2 grid grid-cols-3 gap-2">
                                <input type="text" wire:model="itemTitle" placeholder="Item title" class="col-span-2 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                                <input type="number" wire:model="itemOffsetDays" placeholder="Offset days" class="rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                            </div>
                            <div class="text-xs text-text-muted">Offset is days from join date (onboarding) or termination date (offboarding) — negative means before.</div>
                            <button wire:click="addItem" class="mt-1 self-start text-xs font-semibold text-primary">+ Add item</button>
                            @error('itemTitle') <div class="text-xs text-danger">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>
            @endforeach
            @if($templates->isEmpty())
                <div class="py-6 text-center text-sm text-text-muted">No templates yet.</div>
            @endif
        </div>
    </section>
</div>
