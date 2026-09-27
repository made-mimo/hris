<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C1: "an entitlement-consumption ledger recording exactly which entitlement batch(es) paid for which leave day (supporting split consumption across batches)." One row per (day, batch) pair — a single day can be split across two rows if one batch runs out mid-day (fractional half-days). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlement_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_entitlement_id')->constrained()->cascadeOnDelete();
            $table->decimal('days_consumed', 4, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_consumptions');
    }
};
