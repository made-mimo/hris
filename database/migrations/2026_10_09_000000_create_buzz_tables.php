<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec F1: "Post/Share separation is the key modeling decision" — a Post is
 * the immutable content; every *appearance* of it in the feed (the original
 * posting and every reshare) is its own `post_shares` row with an
 * independent like/comment thread. Photos live on Post via the app's usual
 * public-disk HasMedia pattern rather than a separate child table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('text|photo|video — fixed once created');
            $table->text('body')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamps();
        });

        Schema::create('post_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamps();
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_share_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        // Polymorphic: a like targets either a post_share or a post_comment.
        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('likeable_type');
            $table->unsignedBigInteger('likeable_id');
            $table->timestamps();

            $table->unique(['employee_id', 'likeable_type', 'likeable_id'], 'post_like_unique');
            $table->index(['likeable_type', 'likeable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_shares');
        Schema::dropIfExists('posts');
    }
};
