<?php

use App\Models\Vacancy;
use Livewire\Component;

new class extends Component
{
    public Vacancy $vacancy;

    public function mount(Vacancy $vacancy): void
    {
        abort_unless($vacancy->is_open && $vacancy->is_published, 404);
        $this->vacancy = $vacancy;
    }
};
?>

<x-layouts.public :title="$vacancy->title">
    <a href="{{ route('careers') }}" class="text-muted" style="font-size:13px;">&larr; All positions</a>
    <h1 class="font-display" style="font-size:24px;font-weight:800;margin:10px 0 4px;">{{ $vacancy->title }}</h1>
    <div class="text-muted" style="margin-bottom:20px;font-size:13px;">{{ $vacancy->position_count }} position(s)</div>

    @if($vacancy->description)
        <div class="rounded-md border border-border bg-surface p-5 shadow-sm" style="margin-bottom:24px;white-space:pre-line;">{{ $vacancy->description }}</div>
    @endif

    <livewire:careers-apply-form :vacancy="$vacancy" />
</x-layouts.public>
