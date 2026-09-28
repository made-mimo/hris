<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PermissionService;
use App\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_policy_version',
        'role_id',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Encrypted at rest (spec A1) — Laravel's built-in cast handles
            // encrypt/decrypt transparently using APP_KEY.
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function backupCodes(): HasMany
    {
        return $this->hasMany(TwoFactorBackupCode::class);
    }

    public function emailCodes(): HasMany
    {
        return $this->hasMany(TwoFactorEmailCode::class);
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest();
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** Has this user completed 2FA enrollment (chosen and confirmed a method)? */
    public function hasTwoFactorEnrolled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * The one place either self-service password change or the forgot-
     * password reset should set a new password — PIM/HRIS alignment §3C
     * item 12: a new password revokes every existing API token and trusted
     * device, since any of them may have been issued to whoever knew the
     * old one. Other browser *sessions* are handled separately, by
     * AuthenticateSession (see bootstrap/app.php) noticing the password
     * hash changed on their next request.
     */
    public function setOwnPassword(string $plain): void
    {
        $this->forceFill([
            'password' => $plain,
            'password_policy_version' => Setting::current()->password_policy_version,
        ])->save();

        $revoked = $this->tokens()->count() > 0 || $this->trustedDevices()->count() > 0;

        $this->tokens()->delete();
        $this->trustedDevices()->delete();

        if ($revoked) {
            SecurityEvent::record('tokens_and_devices_revoked', $this, metadata: ['reason' => 'password_changed']);
        }
    }

    /**
     * Broad-strokes "is this person in HR" check for UI sectioning (e.g. the
     * sidebar's "My team"/"Admin" headers) — a legitimate use of the role's
     * own identity (spec A2 names these roles explicitly), distinct from the
     * granular per-screen/per-data-group checks the RBAC engine does for
     * actual access control. See App\Services\PermissionService for those.
     */
    public function isHr(): bool
    {
        return in_array($this->role?->slug, ['admin', 'hr_admin', 'hr_officer'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === 'admin';
    }

    /**
     * NOT named can() — Laravel's own Authorizable trait already defines
     * can($ability, $args) for Gate/Policy checks, and shadowing it here
     * would silently break every @can directive and $user->can(...) call
     * elsewhere in the framework.
     */
    public function canView(string $screenKey): bool
    {
        return app(PermissionService::class)->canViewScreen($this, $screenKey);
    }

    /**
     * The timezone to render this user's timestamps in — their browser-declared
     * IANA zone if captured, else the application default (GMT+1, config('app.timezone')).
     * Never derived from IP/GPS (spec Section C3 excludes location capture).
     */
    public function displayTimezone(): string
    {
        return $this->timezone && in_array($this->timezone, \DateTimeZone::listIdentifiers(), true)
            ? $this->timezone
            : config('app.timezone');
    }
}
