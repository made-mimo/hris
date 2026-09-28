<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security fix: TwoFactorService::verifyTotp() used Google2FA's verifyKey(),
 * which accepts the same valid code more than once inside its time window —
 * a shoulder-surfed or network-sniffed TOTP code could be replayed by
 * anyone who captured it, until it naturally expired. verifyKeyNewer()
 * needs somewhere to persist the timestamp of the last code accepted, so a
 * repeat of that exact code (or an earlier one) is rejected even though
 * it's still numerically "valid."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('two_factor_last_totp_timestamp')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_last_totp_timestamp');
        });
    }
};
