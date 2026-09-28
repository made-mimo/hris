<?php

namespace App\Rules;

use App\Models\Setting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use ZxcvbnPhp\Zxcvbn;

/**
 * Spec Section A1's configurable password policy: min/max length, required
 * character classes, whether spaces are allowed, and — new — a minimum
 * zxcvbn entropy score, read live from Setting::current() rather than
 * hard-coded, so an Admin's policy change takes effect on the very next
 * password set, no deploy required. The entropy check catches what
 * character-class rules alone can't: "P@ssw0rd1!" satisfies every box above
 * while still being one of the first passwords a real cracker tries.
 */
class PasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $settings = Setting::current();

        if (mb_strlen($value) < $settings->password_min_length) {
            $fail("The :attribute must be at least {$settings->password_min_length} characters.");
        }

        if ($settings->password_max_length && mb_strlen($value) > $settings->password_max_length) {
            $fail("The :attribute must not exceed {$settings->password_max_length} characters.");
        }

        if ($settings->password_require_uppercase && ! preg_match('/[A-Z]/u', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
        }

        if ($settings->password_require_lowercase && ! preg_match('/[a-z]/u', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
        }

        if ($settings->password_require_number && ! preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
        }

        if ($settings->password_require_special && ! preg_match('/[^a-zA-Z0-9]/', $value)) {
            $fail('The :attribute must contain at least one special character.');
        }

        if (! $settings->password_allow_spaces && preg_match('/\s/', $value)) {
            $fail('The :attribute must not contain spaces.');
        }

        if ($settings->password_min_zxcvbn_score !== null) {
            $strength = (new Zxcvbn)->passwordStrength($value);

            if ($strength['score'] < $settings->password_min_zxcvbn_score) {
                $reason = $strength['feedback']['warning'] ?: 'This password is too easy to guess.';
                $fail("The :attribute is too weak: {$reason}");
            }
        }
    }
}
