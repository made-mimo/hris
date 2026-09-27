<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B2 Compensation tab: "one or more salary line items ... each tied to a pay grade." Amount and banking details encrypted at rest per spec's explicit call-out for amount, extended here to bank_account_number as the same sensitivity class. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('amount');
            $table->char('currency', 3)->default('NGN');
            $table->string('pay_period')->default('monthly');
            $table->string('bank_name')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_compensations');
    }
};
