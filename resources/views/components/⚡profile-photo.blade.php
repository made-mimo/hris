<?php

use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $photo = null;

    public function save(): void
    {
        $this->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=200,min_height=200']]);

        auth()->user()->employee
            ->addMedia($this->photo->getRealPath())
            ->usingName($this->photo->getClientOriginalName())
            ->toMediaCollection('avatar');

        session()->flash('status', 'Profile picture updated.');

        // Bug fix: this component's own preview updated correctly, but the
        // header avatar (⚡layouts/app.blade.php) is plain Blade computed
        // once at full page load, not part of this Livewire component's
        // subtree — it kept showing the old photo/initials until a full
        // reload. A full-page navigate re-renders the whole layout fresh,
        // fixing that in the same stroke as guaranteeing this component's
        // own $me->avatarUrl() reflects the just-added media rather than a
        // relation Eloquent may have already cached empty earlier in the
        // request.
        $this->redirectRoute('profile', navigate: true);
    }

    public function remove(): void
    {
        auth()->user()->employee->clearMediaCollection('avatar');
        $this->redirectRoute('profile', navigate: true);
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
            <x-file-input model="photo" accept="image/*" :selected="$photo" />
            <div class="hint">JPG, PNG or WEBP, up to 2 MB, at least 200×200px.</div>
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
