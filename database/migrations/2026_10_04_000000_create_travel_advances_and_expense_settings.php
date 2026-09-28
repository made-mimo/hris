<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec E1's "extended this iteration" pieces: an Admin-configurable
 * claim-amount second-approval threshold (unset by default — single-level
 * approval until an Admin sets one), and the Travel Advance & Reconciliation
 * workflow — its own lightweight entity with its own approval, reconciled
 * against claims raised for the same Claim Event rather than an explicit
 * per-claim link (the claim(s) don't exist yet when the advance is raised).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('expense_claim_second_approval_threshold', 12, 2)->nullable();
            $table->unsignedInteger('travel_advance_reconciliation_window_days')->default(30);
        });

        // Spec E1: "an Admin-configurable claim-amount threshold...routes
        // any claim above it through a second, higher-level approver before
        // it can move to Paid" — a new pending_second_approval state between
        // pending_hr and approved.
        Schema::table('expense_claims', function (Blueprint $table) {
            $table->foreignId('second_approved_by')->nullable()->after('hr_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('second_approved_at')->nullable()->after('second_approved_by');
        });

        Schema::create('travel_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('claim_event_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('pending_manager');
            $table->text('rejection_reason')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('expense_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('second_approved_by');
            $table->dropColumn('second_approved_at');
        });

        Schema::dropIfExists('travel_advances');

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['expense_claim_second_approval_threshold', 'travel_advance_reconciliation_window_days']);
        });
    }
};
