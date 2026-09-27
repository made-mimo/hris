<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec Section A1's login/security audit log — auth attempts and 2FA
 * lifecycle events. Deliberately a thin, static-friendly model (mirrors this
 * codebase's Setting::twoFactorEnabled() style) rather than a service class,
 * since recording an event is always exactly "write one row."
 */
class SecurityEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'user_label', 'event', 'method', 'ip_address', 'user_agent', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $event, ?User $user = null, ?string $method = null, array $metadata = []): self
    {
        return static::create([
            'user_id' => $user?->id,
            'user_label' => $user?->email,
            'event' => $event,
            'method' => $method,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    public function label(): string
    {
        return str($this->event)->replace('_', ' ')->ucfirst();
    }
}
