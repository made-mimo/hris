<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A1's configurable password policy: min/max length, required
 * character classes, whether spaces are allowed, and a minimum strength
 * tier — plus "enforce on login," where a policy tightened after an account
 * was created must be satisfied at next login. `password_policy_version` on
 * both tables is how that's tracked: Admin changing the policy bumps
 * `settings.password_policy_version`; a user's own column records which
 * version their current password last satisfied. A mismatch forces a
 * password change before continuing (App\Http\Middleware\EnsurePasswordPolicyMet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('password_min_length')->default(8);
            $table->unsignedSmallInteger('password_max_length')->nullable();
            $table->boolean('password_require_uppercase')->default(true);
            $table->boolean('password_require_lowercase')->default(true);
            $table->boolean('password_require_number')->default(true);
            $table->boolean('password_require_special')->default(false);
            $table->boolean('password_allow_spaces')->default(true);
            $table->unsignedInteger('password_policy_version')->default(1);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('password_policy_version')->default(1)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'password_min_length',
                'password_max_length',
                'password_require_uppercase',
                'password_require_lowercase',
                'password_require_number',
                'password_require_special',
                'password_allow_spaces',
                'password_policy_version',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_policy_version');
        });
    }
};
