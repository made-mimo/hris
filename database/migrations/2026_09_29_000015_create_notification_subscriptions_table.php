<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B1: "Email-notification subscription lists (which addresses receive which system notification categories)." Category is a free-text key (e.g. "leave_approved", "compliance_reminder") rather than an enum — new notification categories get added across the app over time and shouldn't require a schema migration to become subscribable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('email');
            $table->timestamps();

            $table->unique(['category', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_subscriptions');
    }
};
