<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security/usability parity with the companion SI PIM application (PIM/HRIS
 * alignment §3C): per-account lockout tracking on `users`, and a new
 * admin-configurable `settings` field for idle session timeout. CSP mode
 * (item 4) is a deployment/ops concern instead — see config/security.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedInteger('idle_session_timeout_minutes')->default(120);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'locked_until']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['idle_session_timeout_minutes']);
        });
    }
};
