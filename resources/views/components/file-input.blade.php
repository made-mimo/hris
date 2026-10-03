@props([
    'model',
    'live' => false,
    'multiple' => false,
    'accept' => null,
    'selected' => null,
    'label' => 'Choose file',
])
@php
    // A bare native <input type="file"> renders as the browser's own tiny,
    // grey "Choose File" button — easy to miss next to a prominent primary
    // action button, so users hit the real submit button first with
    // nothing selected. Hidden here and paired with a styled label (a
    // label click still triggers its hidden input, standard HTML
    // behavior) in the app's own red primary-button color so it's
    // unambiguous which button starts the two-step "choose, then submit"
    // flow — shared by every file upload in the app (backlog #7) rather
    // than each one hand-rolling its own version of this.
    $inputId = $attributes->get('id') ?: 'file-input-'.str()->random(8);
    $files = $multiple ? ($selected ?? []) : ($selected ? [$selected] : []);
@endphp
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <label for="{{ $inputId }}" class="btn btn-primary btn-sm" style="cursor:pointer;">{{ $label }}</label>
    <input
        id="{{ $inputId }}"
        type="file"
        {{ $live ? 'wire:model.live' : 'wire:model' }}="{{ $model }}"
        @if($accept) accept="{{ $accept }}" @endif
        @if($multiple) multiple @endif
        style="display:none;"
    >
    <span class="hint" style="margin:0;">
        @if(count($files) === 0)
            No file chosen
        @elseif(count($files) === 1)
            {{ $files[0]->getClientOriginalName() }}
        @else
            {{ count($files) }} files selected
        @endif
    </span>
</div>
