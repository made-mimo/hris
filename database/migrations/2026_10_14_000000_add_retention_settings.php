<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec's Non-Functional Requirements: "a data-retention policy for audit
 * logs, login history, and notification logs." Admin-configurable per log
 * type rather than one fixed policy, since the three have different natural
 * retention needs (compliance-relevant audit trail vs. transient
 * notifications) — null means "keep forever" (the default, so this is
 * strictly opt-in and never silently starts deleting data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('audit_log_retention_days')->nullable();
            $table->unsignedSmallInteger('security_event_retention_days')->nullable();
            $table->unsignedSmallInteger('notification_retention_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['audit_log_retention_days', 'security_event_retention_days', 'notification_retention_days']);
        });
    }
};
