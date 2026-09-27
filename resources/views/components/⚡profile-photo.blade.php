<?php

use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $photo = null;

    public function save(): void
    {
        $this->validate(['photo' => ['required', 'image', 'max:2048']]);

        auth()->user()->employee
            ->addMedia($this->photo->getRealPath())
            ->usingName($this->photo->getClientOriginalName())
            ->toMediaCollection('avatar');

        $this->reset('photo');
        session()->flash('status', 'Profile picture updated.');
    }

    public function remove(): void
    {
        auth()->user()->employee->clearMediaCollection('avatar');
    }

    public function with(): array
    {
        return ['me' => auth()->user()->employee];
    }
};
?>

<section class="card">
    <div class="card-header"><h2>Profile picture</h2></div>
    @if(session('status'))
        <div class="pill pill-success" style="margin-bottom:16px;padding:10px 14px;">{{ session('status') }}</div>
    @endif
    <div style="display:flex;align-items:center;gap:20px;">
        <div style="width:88px;height:88px;border-radius:999px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#14151A;color:#fff;font-family:var(--font-display);font-weight:700;font-size:24px;">
            @if($photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">
            @elseif($me->avatarUrl())
                <img src="{{ $me->avatarUrl() }}" alt="{{ $me->fullName() }}" style="width:100%;height:100%;object-fit:cover;">
            @else
                {{ $me->initials }}
            @endif
        </div>
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:10px;">
            <input type="file" wire:model="photo" accept="image/*">
            @error('photo') <div class="hint" style="color:var(--color-danger);">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                @if($me->avatarUrl())
                    <button type="button" wire:click="remove" class="btn btn-outline btn-sm">Remove</button>
                @endif
            </div>
        </form>
    </div>
</section>
