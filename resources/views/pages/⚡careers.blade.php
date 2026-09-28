<?php

use App\Models\Vacancy;
use Livewire\Component;

/** Spec D1: "Public job board: unauthenticated listing... limited to published, open vacancies." */
new class extends Component
{
    public function with(): array
    {
        return [
            'vacancies' => Vacancy::where('is_open', true)->where('is_published', true)->latest()->get(),
        ];
    }
};
?>

<x-layouts.public title="Careers">
    <h1 class="font-display" style="font-size:26px;font-weight:800;margin-bottom:6px;">Careers at Systems Intelligenz</h1>
    <p class="text-muted" style="margin-bottom:24px;">Open positions — apply directly below. <a href="{{ route('careers.feed') }}">RSS feed</a></p>

    <div class="flex flex-col gap-3">
        @foreach($vacancies as $v)
            <a href="{{ route('careers.show', $v) }}" class="rounded-md border border-border bg-surface p-5 shadow-sm" style="display:block;text-decoration:none;">
                <h2 class="font-display text-base font-bold text-text">{{ $v->title }}</h2>
                <div class="text-xs text-text-muted" style="margin-top:4px;">{{ $v->position_count }} position(s) · Posted <span title="{{ $v->created_at->format(\App\Support\Dates::DATE) }}">{{ $v->created_at->diffForHumans() }}</span></div>
                @if($v->description)
                    <p class="text-sm text-text" style="margin-top:8px;">{{ \Illuminate\Support\Str::limit($v->description, 200) }}</p>
                @endif
            </a>
        @endforeach
        @if($vacancies->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No open positions right now — check back soon.</div>
        @endif
    </div>
</x-layouts.public>
