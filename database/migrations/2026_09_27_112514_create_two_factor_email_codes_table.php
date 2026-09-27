<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec A1's Email OTP method: "a 6-digit, single-use code emailed... valid
 * for a short window (e.g., 10 minutes) and rate-limited on resend." Stored
 * hashed like the backup codes, one row per code sent (not overwritten), so
 * a resend doesn't invalidate tracking of the previous attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_email_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_email_codes');
    }
};
