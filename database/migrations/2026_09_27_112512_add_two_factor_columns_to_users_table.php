<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A1: "each user chooses between an authenticator-app (TOTP)
 * and an emailed one-time code as their second factor" — one column records
 * which, `two_factor_secret` only applies to TOTP (encrypted at rest via the
 * model cast, per spec's Section 3.2 encryption-at-rest principle), and
 * `two_factor_confirmed_at` distinguishes "enrolled" from "chose a method
 * but never finished scanning the QR code."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_method')->nullable()->after('timezone'); // "totp" | "email"
            $table->text('two_factor_secret')->nullable()->after('two_factor_method');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_method', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
