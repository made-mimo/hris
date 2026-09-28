<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Spec F1: the immutable content. Photos are multiple, size/type-limited; "type is fixed once a post is created." */
class Post extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const TYPES = ['text', 'photo', 'video'];

    protected $fillable = ['employee_id', 'type', 'body', 'video_url'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk('local');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class);
    }

    /** Spec F1: "a pasted video link resolved to an embeddable form for supported providers" — YouTube and Vimeo only; any other URL is shown as a plain outbound link instead. */
    public function embedUrl(): ?string
    {
        if (! $this->video_url) {
            return null;
        }

        if (preg_match('#youtu\.be/([\w-]+)#', $this->video_url, $m) || preg_match('#youtube\.com/watch\?v=([\w-]+)#', $this->video_url, $m)) {
            return "https://www.youtube.com/embed/{$m[1]}";
        }

        if (preg_match('#vimeo\.com/(\d+)#', $this->video_url, $m)) {
            return "https://player.vimeo.com/video/{$m[1]}";
        }

        return null;
    }
}
