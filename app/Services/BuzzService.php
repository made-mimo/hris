<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Spec F1: "Post/Share separation is the key modeling decision" — creating a
 * post also creates its first PostShare (the original posting) atomically;
 * resharing creates another PostShare pointing at the same Post, with its
 * own independent like/comment thread.
 */
class BuzzService
{
    public function createPost(Employee $author, string $type, ?string $body, ?string $videoUrl, array $photos = []): PostShare
    {
        return DB::transaction(function () use ($author, $type, $body, $videoUrl, $photos) {
            $post = Post::create([
                'employee_id' => $author->id,
                'type' => $type,
                'body' => $body,
                'video_url' => $videoUrl,
            ]);

            foreach ($photos as $photo) {
                $post->addMedia($photo)->toMediaCollection('photos');
            }

            return PostShare::create(['post_id' => $post->id, 'employee_id' => $author->id]);
        });
    }

    public function reshare(Post $post, Employee $employee, ?string $caption): PostShare
    {
        return PostShare::create(['post_id' => $post->id, 'employee_id' => $employee->id, 'caption' => $caption]);
    }

    public function addComment(PostShare $share, Employee $author, string $body): PostComment
    {
        return DB::transaction(function () use ($share, $author, $body) {
            $comment = PostComment::create(['post_share_id' => $share->id, 'employee_id' => $author->id, 'body' => $body]);
            $share->increment('comment_count');

            return $comment;
        });
    }

    /** @return bool true if now liked, false if the like was removed (toggle) */
    public function toggleLike(Model $likeable, Employee $employee): bool
    {
        return DB::transaction(function () use ($likeable, $employee) {
            $existing = PostLike::where('employee_id', $employee->id)
                ->where('likeable_type', get_class($likeable))
                ->where('likeable_id', $likeable->id)
                ->first();

            if ($existing) {
                $existing->delete();
                if ($likeable instanceof PostShare) {
                    $likeable->decrement('like_count');
                }

                return false;
            }

            PostLike::create(['employee_id' => $employee->id, 'likeable_type' => get_class($likeable), 'likeable_id' => $likeable->id]);
            if ($likeable instanceof PostShare) {
                $likeable->increment('like_count');
            }

            return true;
        });
    }

    /** Spec F1: "ownership-based edit/delete rights...via the standard data-group permission model" — the post's own author, or a moderator (data-group scope 'all'). */
    public function canManagePost(Post $post, User $user, PermissionService $permissions): bool
    {
        $employee = $user->employee;

        return ($employee && $post->employee_id === $employee->id) || $permissions->scopeFor($user, 'buzz') === 'all';
    }

    public function canManageShare(PostShare $share, User $user, PermissionService $permissions): bool
    {
        $employee = $user->employee;

        return ($employee && $share->employee_id === $employee->id) || $permissions->scopeFor($user, 'buzz') === 'all';
    }

    public function canManageComment(PostComment $comment, User $user, PermissionService $permissions): bool
    {
        $employee = $user->employee;

        return ($employee && $comment->employee_id === $employee->id) || $permissions->scopeFor($user, 'buzz') === 'all';
    }

    public function deleteShare(PostShare $share): void
    {
        DB::transaction(function () use ($share) {
            $isOriginalPosting = $share->post->shares()->oldest('id')->value('id') === $share->id;

            $share->comments()->each(fn (PostComment $c) => $c->likes()->delete());
            $share->likes()->delete();
            $share->comments()->delete();
            $share->delete();

            // Deleting the original posting removes the underlying Post
            // (and every reshare of it) entirely — there's nothing left to
            // reshare from once the source content is gone.
            if ($isOriginalPosting) {
                $post = $share->post;
                $post->shares->each(fn (PostShare $s) => $this->deleteShare($s->fresh()));
                $post->delete();
            }
        });
    }

    public function deleteComment(PostComment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $comment->likes()->delete();
            $comment->share()->decrement('comment_count');
            $comment->delete();
        });
    }

    /** Spec F1: "a periodic reconciliation job as a correctness backstop" — recomputes every share's denormalized counts from source. */
    public function reconcileCounts(): void
    {
        PostShare::query()->chunkById(200, function ($shares) {
            foreach ($shares as $share) {
                $share->update([
                    'like_count' => $share->likes()->count(),
                    'comment_count' => $share->comments()->count(),
                ]);
            }
        });
    }
}
