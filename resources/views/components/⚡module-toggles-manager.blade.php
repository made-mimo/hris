<?php

use App\Models\ModuleToggle;
use Livewire\Component;

new class extends Component
{
    public function toggle(int $id): void
    {
        $module = ModuleToggle::findOrFail($id);
        $module->update(['is_enabled' => ! $module->is_enabled]);
        session()->flash('status', "{$module->label} ".($module->is_enabled ? 'enabled' : 'disabled').'.');
    }

    public function with(): array
    {
        return ['modules' => ModuleToggle::orderBy('label')->get()];
    }
};
?>

<div>
    @if(session('status'))
        <div class="mb-4 inline-flex items-center gap-2 rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <div class="mb-3 text-xs text-text-muted">Disabling a module hides its nav entry and blocks direct access for everyone, regardless of role.</div>
        <div class="divide-y divide-border">
            @foreach($modules as $m)
                <div class="flex items-center justify-between py-2.5">
                    <span class="text-sm font-medium text-text">{{ $m->label }}</span>
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-text">
                        <input type="checkbox" wire:click="toggle({{ $m->id }})" @checked($m->is_enabled) class="h-4 w-4 accent-primary">
                        {{ $m->is_enabled ? 'Enabled' : 'Disabled' }}
                    </label>
                </div>
            @endforeach
        </div>
    </section>
</div>
