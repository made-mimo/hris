<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Singleton row (id=1) holding project-wide, Admin-configurable options.
 * Stand-in for the spec's System Configuration/Branding module (Section A5).
 */
class Setting extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    /** See Employee::$auditExcept for why — Auditable diffs post-cast values, so an encrypted field must be excluded or it leaks its decrypted plaintext into audit_logs.changes. */
    protected array $auditExcept = ['smtp_password', 'sso_client_secret'];

    protected $fillable = [
        'company_name', 'two_factor_enabled',
        'password_min_length', 'password_max_length', 'password_require_uppercase',
        'password_require_lowercase', 'password_require_number', 'password_require_special',
        'password_allow_spaces', 'password_policy_version',
        'employee_id_format', 'employee_id_sequence_scope',
        'tax_id', 'registration_number', 'address', 'contact_email', 'contact_phone',
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
        'mail_from_address', 'mail_from_name',
        'sso_enabled', 'sso_provider', 'sso_client_id', 'sso_client_secret', 'sso_endpoint', 'sso_domain',
        'health_check_hidden', 'show_optional_profile_fields',
    ];

    protected function casts(): array
    {
        return [
            'two_factor_enabled' => 'boolean',
            'password_require_uppercase' => 'boolean',
            'password_require_lowercase' => 'boolean',
            'password_require_number' => 'boolean',
            'password_require_special' => 'boolean',
            'password_allow_spaces' => 'boolean',
            'smtp_password' => 'encrypted',
            'sso_enabled' => 'boolean',
            'sso_client_secret' => 'encrypted',
            'health_check_hidden' => 'boolean',
            'show_optional_profile_fields' => 'boolean',
        ];
    }

    /**
     * Bumps automatically whenever the policy fields actually change (not on
     * every save) — this is what "enforce on login" compares a user's own
     * `password_policy_version` against.
     */
    protected static function booted(): void
    {
        static::saving(function (self $settings) {
            $policyFields = [
                'password_min_length', 'password_max_length', 'password_require_uppercase',
                'password_require_lowercase', 'password_require_number', 'password_require_special',
                'password_allow_spaces',
            ];

            if ($settings->exists && $settings->isDirty($policyFields)) {
                $settings->password_policy_version++;
            }
        });
    }

    /** Company logo storage (spec Section 3.5) — see App\Models\Employee for the same pattern. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public static function forget(): void
    {
        // No cache layer — caching a raw Eloquent model across PHP-built-in-
        // server request processes is fragile (unserialize can come back as
        // __PHP_Incomplete_Class); this single-row lookup is cheap enough
        // to just query fresh each time.
    }

    public static function twoFactorEnabled(): bool
    {
        return (bool) static::current()->two_factor_enabled;
    }
}
