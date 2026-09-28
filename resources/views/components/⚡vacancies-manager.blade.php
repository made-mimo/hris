<?php

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Vacancy;
use App\Services\PermissionService;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Spec D1: Vacancies — "title, description, position count, open/closed flag, published-to-public-feed flag, hiring manager, linked requisition, attachments." */
new class extends Component
{
    use WithFileUploads;

    public string $title = '';

    public ?int $jobTitleId = null;

    public string $description = '';

    public int $positionCount = 1;

    public ?int $hiringManagerId = null;

    public ?int $uploadingFor = null;

    public $file = null;

    public function create(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'jobTitleId' => ['nullable', 'exists:job_titles,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'positionCount' => ['required', 'integer', 'min:1'],
            'hiringManagerId' => ['required', 'exists:employees,id'],
        ]);

        Vacancy::create([
            'title' => $data['title'],
            'job_title_id' => $data['jobTitleId'],
            'description' => $data['description'],
            'position_count' => $data['positionCount'],
            'hiring_manager_id' => $data['hiringManagerId'],
        ]);

        $this->reset('title', 'jobTitleId', 'description', 'positionCount', 'hiringManagerId');
        session()->flash('status', 'Vacancy created.');
    }

    public function toggleOpen(int $id): void
    {
        $vacancy = Vacancy::findOrFail($id);
        $vacancy->update(['is_open' => ! $vacancy->is_open]);
    }

    public function togglePublished(int $id): void
    {
        $vacancy = Vacancy::findOrFail($id);
        $vacancy->update(['is_published' => ! $vacancy->is_published]);
    }

    public function upload(): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240']]);

        Vacancy::findOrFail($this->uploadingFor)->addMedia($this->file->getRealPath())
            ->usingName($this->file->getClientOriginalName())
            ->toMediaCollection('attachments');

        $this->reset('file', 'uploadingFor');
        session()->flash('status', 'Attachment uploaded.');
    }

    public function with(PermissionService $permissions): array
    {
        $user = auth()->user();
        $scope = $permissions->scopeFor($user, 'recruitment');
        $me = $user->employee;

        return [
            'vacancies' => Vacancy::with(['hiringManager', 'jobTitle', 'applications'])
                ->when($scope !== 'all', fn ($q) => $q->where('hiring_manager_id', $me?->id))
                ->latest()
                ->get(),
            'employees' => Employee::orderBy('last_name')->get(),
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
        <h2 class="mb-3.5 font-display text-base font-bold text-text">New vacancy</h2>
        <form wire:submit="create" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Title</label>
                    <input type="text" wire:model="title" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('title') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Job title</label>
                    <select wire:model="jobTitleId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— none —</option>
                        @foreach($jobTitles as $jt)<option value="{{ $jt->id }}">{{ $jt->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Number of positions</label>
                    <input type="number" min="1" wire:model="positionCount" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-text">Hiring manager</label>
                    <select wire:model="hiringManagerId" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                        <option value="">— select —</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach
                    </select>
                    @error('hiringManagerId') <div class="mt-1 text-xs text-danger">{{ $message }}</div> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-text">Description</label>
                <textarea wire:model="description" rows="3" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary" placeholder="Shown on the public job board once published."></textarea>
            </div>
            <button type="submit" class="self-start rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Create vacancy</button>
        </form>
    </section>

    <div class="flex flex-col gap-3">
        @foreach($vacancies as $v)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-display text-sm font-bold text-text">{{ $v->title }}</h3>
                        <div class="text-xs text-text-muted">{{ $v->hiringManager->fullName() }} · {{ $v->position_count }} position(s) · {{ $v->applications->count() }} applicant(s)</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $v->is_open ? 'bg-accent-light text-accent' : 'bg-text-faint/15 text-text-muted' }}">{{ $v->is_open ? 'Open' : 'Closed' }}</span>
                        <span class="rounded-pill px-2.5 py-1 text-xs font-semibold {{ $v->is_published ? 'bg-info-light text-info' : 'bg-text-faint/15 text-text-muted' }}">{{ $v->is_published ? 'Published' : 'Unpublished' }}</span>
                        <button wire:click="toggleOpen({{ $v->id }})" class="text-xs font-semibold text-primary">{{ $v->is_open ? 'Close' : 'Reopen' }}</button>
                        <button wire:click="togglePublished({{ $v->id }})" class="text-xs font-semibold text-primary">{{ $v->is_published ? 'Unpublish' : 'Publish' }}</button>
                    </div>
                </div>
                @if($v->description)
                    <p class="mb-2 text-xs text-text-muted">{{ $v->description }}</p>
                @endif

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @foreach($v->getMedia('attachments') as $media)
                        <a href="{{ route('private-media.show', $media) }}" target="_blank" class="rounded-pill bg-text-faint/15 px-2.5 py-1 text-xs font-semibold text-text hover:bg-text-faint/25">{{ $media->name }}</a>
                    @endforeach
                    @if($uploadingFor === $v->id)
                        <input type="file" wire:model="file" class="text-xs">
                        <button wire:click="upload" class="text-xs font-semibold text-primary">Upload</button>
                        <button wire:click="$set('uploadingFor', null)" class="text-xs font-semibold text-text-muted">Cancel</button>
                        @error('file') <div class="text-xs text-danger">{{ $message }}</div> @enderror
                    @else
                        <button wire:click="$set('uploadingFor', {{ $v->id }})" class="text-xs font-semibold text-primary">+ Attach document</button>
                    @endif
                </div>
            </section>
        @endforeach
        @if($vacancies->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No vacancies yet.</div>
        @endif
    </div>
</div>
