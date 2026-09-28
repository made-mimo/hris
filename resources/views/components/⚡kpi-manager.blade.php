<?php

use App\Models\JobTitle;
use App\Models\Kpi;
use Livewire\Component;

new class extends Component
{
    public string $title = '';

    public float $minScale = 1;

    public float $maxScale = 5;

    public ?int $jobTitleId = null;

    public function create(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'minScale' => ['required', 'numeric', 'lt:maxScale'],
            'maxScale' => ['required', 'numeric', 'gt:minScale'],
            'jobTitleId' => ['nullable', 'exists:job_titles,id'],
        ]);

        Kpi::create([
            'title' => $data['title'],
            'min_scale' => $data['minScale'],
            'max_scale' => $data['maxScale'],
            'job_title_id' => $data['jobTitleId'],
        ]);

        $this->reset('title', 'jobTitleId');
        $this->minScale = 1;
        $this->maxScale = 5;
        session()->flash('status', 'KPI added.');
    }

    /** Spec D2: "soft-deleted rather than hard-deleted once used" — always soft-deleted, never a real DELETE. */
    public function remove(int $id): void
    {
        Kpi::findOrFail($id)->delete();
        session()->flash('status', 'KPI removed.');
    }

    public function restore(int $id): void
    {
        Kpi::withTrashed()->findOrFail($id)->restore();
        session()->flash('status', 'KPI restored.');
    }

    public function with(): array
    {
        return [
            'kpis' => Kpi::withTrashed()->with('jobTitle')->orderBy('title')->get(),
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New KPI</h2>
        <form wire:submit="create" class="flex flex-wrap items-end gap-3">
            <div style="flex:1;min-width:200px;">
                <label class="mb-1.5 block text-xs font-semibold text-text">Title</label>
                <input type="text" wire:model="title" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                @error('title') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Min scale</label>
                <input type="number" step="0.5" wire:model="minScale" class="w-24 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Max scale</label>
                <input type="number" step="0.5" wire:model="maxScale" class="w-24 rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Job title</label>
                <select wire:model="jobTitleId" class="rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    <option value="">— default (all job titles) —</option>
                    @foreach($jobTitles as $jt)<option value="{{ $jt->id }}">{{ $jt->name }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add KPI</button>
        </form>
        @error('minScale') <div class="mt-2 text-xs text-danger">{{ $message }}</div> @enderror
    </section>

    <section class="overflow-x-auto rounded-md border border-border bg-surface shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-2.5">Title</th>
                    <th class="px-4 py-2.5">Scale</th>
                    <th class="px-4 py-2.5">Applies to</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($kpis as $kpi)
                    <tr class="border-b border-border last:border-0 {{ $kpi->trashed() ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2.5 text-text">{{ $kpi->title }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $kpi->min_scale }}–{{ $kpi->max_scale }}</td>
                        <td class="px-4 py-2.5 text-text">{{ $kpi->jobTitle?->name ?? 'Default (all)' }}</td>
                        <td class="px-4 py-2.5">
                            @if($kpi->trashed())
                                <button wire:click="restore({{ $kpi->id }})" class="text-xs font-semibold text-primary">Restore</button>
                            @else
                                <button wire:click="remove({{ $kpi->id }})" wire:confirm="Remove this KPI?" class="text-xs font-semibold text-danger">Remove</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if($kpis->isEmpty())
                    <tr><td class="px-4 py-6 text-center text-text-muted" colspan="4">No KPIs configured yet.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</div>
