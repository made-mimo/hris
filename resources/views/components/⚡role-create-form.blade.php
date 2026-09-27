<?php

use App\Models\Role;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public ?int $cloneFromId = null;

    public function mount(): void
    {
        $this->cloneFromId = Role::where('slug', 'hr_officer')->value('id');
    }

    /**
     * "Clone role" (spec A2): "a new role is typically built as 'start from
     * HR Officer, then add X' rather than from a blank matrix." There is no
     * blank-matrix path at all here — every custom role starts as a full
     * copy of an existing one's screens + data-group grants.
     */
    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'cloneFromId' => ['required', 'exists:roles,id'],
        ]);

        $source = Role::findOrFail($this->cloneFromId);
        $slug = Str::slug($this->name, '_');
        $clone = $source->cloneAs($this->name, $slug);

        $this->redirectRoute('admin.roles.edit', $clone, navigate: true);
    }

    public function with(): array
    {
        return ['roles' => Role::orderBy('name')->get()];
    }
};
?>

<section class="card">
    <div class="card-header"><h2>New custom role</h2></div>
    <form wire:submit="create" style="display:flex;flex-direction:column;gap:14px;">
        <div class="field" style="margin:0;">
            <label for="cloneFromId">Start from</label>
            <select id="cloneFromId" wire:model="cloneFromId">
                @foreach($roles as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
            </select>
            <div class="hint">Copies its full screen + data-group matrix as a starting point.</div>
        </div>
        <div class="field" style="margin:0;">
            <label for="name">New role name</label>
            <input id="name" type="text" wire:model="name" placeholder="e.g. Regional HR Officer">
            @error('name') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="btn btn-primary" style="justify-content:center;">Create and edit matrix</button>
    </form>
</section>
