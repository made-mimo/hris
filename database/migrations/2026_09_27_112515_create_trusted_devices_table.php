<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec A1: "a Trusted Devices table (user, device fingerprint/token, label,
 * first-trusted date, expiry date, last-used date, IP/user-agent at trust
 * time)" plus a self-service "Manage Trusted Devices" screen with per-device
 * revoke. `token_hash` replaces this prototype's earlier, insecure
 * cookie-holds-the-user-id approach — the cookie now carries an opaque,
 * high-entropy token whose hash alone is stored, same pattern as Laravel's
 * own remember-token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('label')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('trusted_at');
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
