<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section 3.2's Renewal & Compliance Reminder Engine: "one generic
 * engine is configured per renewable-entity type: a renewable record (with
 * issue/expiry dates), an Admin-configurable set of reminder tiers (how
 * many, and how many days before expiry each fires), and a notify list
 * (roles and/or named individuals)." Built ahead of its three named
 * consumers (Vehicle Renewals E3, Asset warranties E2, Company Registration
 * Documents E5 — all Phase 4, none exist in this prototype yet) per the
 * spec's own reasoning: "building it once, early, is the entire point of
 * generalizing it."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('renewal_reminder_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before_expiry');
            $table->timestamps();

            $table->unique(['renewal_type_id', 'days_before_expiry']);
        });

        Schema::create('renewal_notify_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('renewables', function (Blueprint $table) {
            $table->id();
            $table->string('renewable_type');
            $table->unsignedBigInteger('renewable_id');
            $table->foreignId('renewal_type_id')->constrained()->cascadeOnDelete();
            $table->date('expiry_date');
            $table->string('status')->default('active'); // active | expired | retired
            $table->json('fired_tier_ids')->nullable();
            $table->timestamp('last_expired_alert_at')->nullable();
            $table->timestamps();

            $table->index(['renewable_type', 'renewable_id']);
            $table->index(['status', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewables');
        Schema::dropIfExists('renewal_notify_targets');
        Schema::dropIfExists('renewal_reminder_tiers');
        Schema::dropIfExists('renewal_types');
    }
};
