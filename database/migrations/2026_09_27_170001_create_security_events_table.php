<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A1: "a login audit log (user, role, timestamp, 2FA outcome,
 * 2FA method used)" and "all 2FA lifecycle events... are written to the
 * login/security audit log" — a separate log from the generic entity-
 * mutation `audit_logs` table, since these are authentication/session
 * events, not model mutations. Nullable `user_id` because a failed login
 * with a bad email has no user to attach to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_label')->nullable();
            $table->string('event'); // login_succeeded, login_failed, two_factor_enrolled, ...
            $table->string('method')->nullable(); // totp | email, when relevant
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index('user_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
