<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C2: "One timesheet per employee per configured weekly period, auto-resolved from any date." Status here is the header-level workflow WorkflowEngine drives (not_submitted -> submitted -> approved, with rejected supporting resubmission — unlike Leave's terminal rejection). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->string('status')->default('not_submitted')->comment('not_submitted | submitted | approved | rejected');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'week_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
