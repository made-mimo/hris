<?php

use App\Models\Employee;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostShare;
use App\Services\BuzzService;
use App\Services\PermissionService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/** Spec F1: Post/Share separation — the feed lists PostShare rows (every appearance, original or reshare), each with its own independent like/comment thread. */
new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $type = 'text';

    public string $body = '';

    public string $videoUrl = '';

    public $photos = [];

    public ?int $reshareTargetId = null;

    public string $reshareCaption = '';

    public array $commentBoxOpen = [];

    public array $newComment = [];

    public function createPost(BuzzService $buzz): void
    {
        $rules = ['type' => ['required', 'in:'.implode(',', Post::TYPES)]];
        if ($this->type === 'text') {
            $rules['body'] = ['required', 'string', 'max:2000'];
        } elseif ($this->type === 'video') {
            $rules['videoUrl'] = ['required', 'url'];
            $rules['body'] = ['nullable', 'string', 'max:2000'];
        } else {
            $rules['photos'] = ['required', 'array', 'min:1', 'max:6'];
            $rules['photos.*'] = ['image', 'max:2048'];
            $rules['body'] = ['nullable', 'string', 'max:2000'];
        }

        $data = $this->validate($rules);

        $employee = auth()->user()->employee;
        $buzz->createPost(
            $employee,
            $this->type,
            $data['body'] ?? null,
            $this->type === 'video' ? $data['videoUrl'] : null,
            $this->type === 'photo' ? $this->photos : [],
        );

        $this->reset('body', 'videoUrl', 'photos');
        $this->type = 'text';
        session()->flash('status', 'Posted.');
    }

    public function reshare(int $postId, BuzzService $buzz): void
    {
        $buzz->reshare(Post::findOrFail($postId), auth()->user()->employee, $this->reshareCaption ?: null);
        $this->reset('reshareTargetId', 'reshareCaption');
        session()->flash('status', 'Reshared.');
    }

    public function toggleLike(int $shareId, BuzzService $buzz): void
    {
        $buzz->toggleLike(PostShare::findOrFail($shareId), auth()->user()->employee);
    }

    public function toggleCommentLike(int $commentId, BuzzService $buzz): void
    {
        $buzz->toggleLike(PostComment::findOrFail($commentId), auth()->user()->employee);
    }

    public function addComment(int $shareId, BuzzService $buzz): void
    {
        $body = trim($this->newComment[$shareId] ?? '');
        if ($body === '') {
            return;
        }

        $buzz->addComment(PostShare::findOrFail($shareId), auth()->user()->employee, $body);
        $this->newComment[$shareId] = '';
    }

    public function deleteShare(int $shareId, BuzzService $buzz, PermissionService $permissions): void
    {
        $share = PostShare::findOrFail($shareId);
        abort_unless($buzz->canManageShare($share, auth()->user(), $permissions), 403);
        $buzz->deleteShare($share);
        session()->flash('status', 'Deleted.');
    }

    public function deleteComment(int $commentId, BuzzService $buzz, PermissionService $permissions): void
    {
        $comment = PostComment::findOrFail($commentId);
        abort_unless($buzz->canManageComment($comment, auth()->user(), $permissions), 403);
        $buzz->deleteComment($comment);
    }

    public function with(PermissionService $permissions): array
    {
        $me = auth()->user()->employee;
        $user = auth()->user();

        $shares = PostShare::query()
            ->with(['post.author', 'post.media', 'sharedBy', 'comments.author', 'comments.likes'])
            ->whereHas('post.author', fn ($q) => $q->where('is_gdpr_purged', false))
            ->whereHas('sharedBy', fn ($q) => $q->where('is_gdpr_purged', false))
            ->latest()
            ->paginate(10);

        $today = now()->startOfDay();
        $anniversaries = Employee::whereNotNull('hire_date')
            ->where('is_gdpr_purged', false)
            ->whereDoesntHave('terminations')
            ->get()
            ->map(function (Employee $e) use ($today) {
                $nextAnniversary = $e->hire_date->copy()->year($today->year);
                if ($nextAnniversary->lt($today)) {
                    $nextAnniversary->addYear();
                }

                return ['employee' => $e, 'date' => $nextAnniversary, 'years' => $nextAnniversary->year - $e->hire_date->year];
            })
            ->filter(fn (array $row) => $row['date']->between($today, $today->copy()->addDays(7)) && $row['years'] > 0)
            ->sortBy(fn (array $row) => $row['date'])
            ->values();

        return [
            'shares' => $shares,
            'me' => $me,
            'user' => $user,
            'buzzService' => app(BuzzService::class),
            'permissions' => $permissions,
            'upcomingAnniversaries' => $anniversaries,
        ];
    }
};
?>

<div class="grid" style="grid-template-columns:2fr 1fr;gap:16px;align-items:start;">
    <div class="flex flex-col gap-4">
        @if(session('status'))
            <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
        @endif

        <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <form wire:submit="createPost" class="flex flex-col gap-3">
                <div class="flex gap-3 text-xs">
                    <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="type" value="text" class="accent-primary"> Text</label>
                    <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="type" value="photo" class="accent-primary"> Photo</label>
                    <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="type" value="video" class="accent-primary"> Video</label>
                </div>

                @if($type === 'video')
                    <input type="text" wire:model.live="videoUrl" placeholder="Paste a YouTube or Vimeo link" class="w-full rounded-sm border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary">
                    @error('videoUrl') <div class="text-xs text-danger">{{ $message }}</div> @enderror
                @endif

                @if($type === 'photo')
                    <input type="file" wire:model.live="photos" multiple class="w-full text-xs">
                    @error('photos') <div class="text-xs text-danger">{{ $message }}</div> @enderror
                    @error('photos.*') <div class="text-xs text-danger">{{ $message }}</div> @enderror
                @endif

                <textarea wire:model.live="body" rows="2" placeholder="{{ $type === 'text' ? 'What is on your mind?' : 'Add a caption (optional)' }}" class="w-full rounded-sm border border-border bg-surface px-3.5 py-2.5 text-sm text-text outline-none focus:border-primary"></textarea>
                @error('body') <div class="text-xs text-danger">{{ $message }}</div> @enderror

                <button type="submit" wire:loading.attr="disabled" class="self-start rounded-sm bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Post</button>
            </form>
        </section>

        @foreach($shares as $share)
            <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold text-text">{{ $share->sharedBy->fullName() }}</div>
                        <div class="text-xs text-text-muted">{{ $share->created_at->diffForHumans() }}{{ $share->post->employee_id !== $share->employee_id ? ' · shared a post' : '' }}</div>
                    </div>
                    @if($buzzService->canManageShare($share, $user, $permissions))
                        <button wire:click="deleteShare({{ $share->id }})" wire:confirm="Delete this?" class="text-xs font-semibold text-danger">Delete</button>
                    @endif
                </div>

                @if($share->caption)
                    <div class="mt-2 text-sm text-text">{{ $share->caption }}</div>
                @endif

                <div class="mt-2.5 rounded-sm border border-border bg-bg p-3.5">
                    <div class="mb-1.5 text-xs font-semibold text-text-muted">{{ $share->post->author->fullName() }}</div>
                    @if($share->post->body)
                        <div class="text-sm text-text">{{ $share->post->body }}</div>
                    @endif
                    @if($share->post->type === 'photo' && $share->post->getMedia('photos')->isNotEmpty())
                        <div class="mt-2 grid" style="grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:6px;">
                            @foreach($share->post->getMedia('photos') as $media)
                                <img src="{{ route('private-media.show', $media) }}" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:6px;">
                            @endforeach
                        </div>
                    @endif
                    @if($share->post->type === 'video')
                        @if($share->post->embedUrl())
                            <div class="mt-2" style="position:relative;padding-bottom:56.25%;height:0;">
                                <iframe src="{{ $share->post->embedUrl() }}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;border-radius:6px;" allowfullscreen></iframe>
                            </div>
                        @else
                            <a href="{{ $share->post->video_url }}" target="_blank" class="mt-1 block text-xs font-semibold text-primary">{{ $share->post->video_url }}</a>
                        @endif
                    @endif
                </div>

                <div class="mt-3 flex items-center gap-3 border-t border-border pt-2.5 text-xs">
                    <button wire:click="toggleLike({{ $share->id }})" class="font-semibold {{ $share->isLikedBy($me) ? 'text-primary' : 'text-text-muted' }}">👍 Like ({{ $share->like_count }})</button>
                    <button wire:click="$set('commentBoxOpen.{{ $share->id }}', {{ ($commentBoxOpen[$share->id] ?? false) ? 'false' : 'true' }})" class="font-semibold text-text-muted">💬 Comment ({{ $share->comment_count }})</button>
                    <button wire:click="$set('reshareTargetId', {{ $reshareTargetId === $share->post_id ? 'null' : $share->post_id }})" class="font-semibold text-text-muted">🔁 Reshare</button>
                </div>

                @if($reshareTargetId === $share->post_id)
                    <div class="mt-2 flex gap-2">
                        <input type="text" wire:model="reshareCaption" placeholder="Add a caption (optional)" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                        <button wire:click="reshare({{ $share->post_id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Share</button>
                    </div>
                @endif

                @if($commentBoxOpen[$share->id] ?? false)
                    <div class="mt-2.5 flex flex-col gap-2 border-t border-border pt-2.5">
                        @foreach($share->comments as $comment)
                            <div class="flex items-start justify-between gap-2 text-xs">
                                <div>
                                    <span class="font-semibold text-text">{{ $comment->author->fullName() }}</span>
                                    <span class="text-text-muted">{{ $comment->body }}</span>
                                    <button wire:click="toggleCommentLike({{ $comment->id }})" class="ml-1.5 font-semibold {{ $comment->isLikedBy($me) ? 'text-primary' : 'text-text-muted' }}">👍 {{ $comment->likes->count() }}</button>
                                </div>
                                @if($buzzService->canManageComment($comment, $user, $permissions))
                                    <button wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment?" class="text-danger">Remove</button>
                                @endif
                            </div>
                        @endforeach
                        <div class="flex gap-2">
                            <input type="text" wire:model="newComment.{{ $share->id }}" placeholder="Write a comment…" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-1.5 text-xs text-text outline-none focus:border-primary">
                            <button wire:click="addComment({{ $share->id }})" class="rounded-sm bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Send</button>
                        </div>
                    </div>
                @endif
            </section>
        @endforeach

        @if($shares->isEmpty())
            <div class="rounded-md border border-border bg-surface p-6 text-center text-sm text-text-muted shadow-sm">No posts yet — be the first to share something.</div>
        @endif

        <div>{{ $shares->links() }}</div>
    </div>

    <aside class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3 font-display text-sm font-bold text-text">Upcoming work anniversaries</h2>
        @forelse($upcomingAnniversaries as $row)
            <div class="mb-2 text-xs text-text-muted">{{ $row['employee']->fullName() }} — {{ $row['years'] }} year(s) on {{ $row['date']->format('j M') }}</div>
        @empty
            <div class="text-xs text-text-muted">No anniversaries in the next 7 days.</div>
        @endforelse
    </aside>
</div>
