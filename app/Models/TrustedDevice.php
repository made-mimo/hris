<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id', 'token_hash', 'label', 'ip_address', 'user_agent',
        'trusted_at', 'expires_at', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'trusted_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** A rough, best-effort browser/OS guess from the user-agent for display only. */
    public function deviceGuess(): string
    {
        $ua = $this->user_agent ?? '';

        return match (true) {
            str_contains($ua, 'Android') => 'Android device',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS device',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows PC',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown device',
        };
    }
}
