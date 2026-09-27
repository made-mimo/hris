<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec C1: "a Leave Request header with one child record per calendar day
 * in the requested range (so weekends/holidays inside a range get their
 * own inert status and are excluded from both approval and entitlement
 * consumption)." Day status is finer than the header's workflow status:
 * pending (mirrors header while awaiting approval) -> scheduled (header
 * approved, date still in the future) -> taken (date has passed — flipped
 * by the daily scheduled job, spec's "automatic day-to-day status
 * progression") -> rejected/cancelled (mirrors a header rejection/
 * cancellation) -> inert (non-working day: weekend or holiday, permanent,
 * never transitions, never counts toward days or consumption).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_working_day')->default(true);
            $table->string('duration_type')->default('full')->comment('full | am | pm | time');
            $table->decimal('hours', 5, 2)->nullable()->comment('for duration_type=time');
            $table->decimal('day_value', 4, 2)->default(1)->comment('1 = full day, 0.5 = half day, toward days/entitlement consumed');
            $table->string('status')->default('pending')->comment('pending | scheduled | taken | rejected | cancelled | inert');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['leave_request_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_days');
    }
};
