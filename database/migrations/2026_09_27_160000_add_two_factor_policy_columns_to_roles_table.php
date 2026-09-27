<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A1's admin policy control: "the Admin role can, org-wide or
 * per-role, require 2FA enrollment, restrict which method(s) are permitted
 * ... require TOTP specifically for elevated roles such as Admin/HR Admin
 * while leaving Email OTP available to ESS ... disable the trusted-device
 * skip ... and set the trusted-device expiry window."
 *
 * `Setting.two_factor_enabled` remains the project-wide master switch (off
 * disables 2FA for everyone, full stop, regardless of these columns); these
 * per-role columns only refine behavior once that master switch is on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('two_factor_required')->default(true)->after('is_situational');
            // Null/empty = both TOTP and email are allowed (the default).
            $table->json('two_factor_allowed_methods')->nullable()->after('two_factor_required');
            $table->boolean('two_factor_trusted_device_allowed')->default(true)->after('two_factor_allowed_methods');
            // Null = fall back to config('twofactor.trusted_device_days').
            $table->unsignedSmallInteger('two_factor_trusted_device_days')->nullable()->after('two_factor_trusted_device_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_required',
                'two_factor_allowed_methods',
                'two_factor_trusted_device_allowed',
                'two_factor_trusted_device_days',
            ]);
        });
    }
};
