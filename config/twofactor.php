<?php

/**
 * Spec Section A1 policy knobs — admin-tunable without code changes, even
 * though there's no dedicated UI for them yet (they sit alongside the
 * Setting.two_factor_enabled project-wide switch as plain env config).
 */
return [
    'trusted_device_days' => (int) env('TWO_FACTOR_TRUSTED_DEVICE_DAYS', 30),
    'backup_codes_count' => (int) env('TWO_FACTOR_BACKUP_CODES_COUNT', 10),
    'email_code_ttl_minutes' => (int) env('TWO_FACTOR_EMAIL_CODE_TTL_MINUTES', 10),
    'email_resend_cooldown_seconds' => (int) env('TWO_FACTOR_EMAIL_RESEND_COOLDOWN_SECONDS', 60),
];
