<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec B2: "Ad-hoc and predefined reporting ... exportable (CSV/PDF) and
 * schedulable for recurring email delivery to a configurable recipient
 * list." `config` freezes the report builder's field/filter selection at
 * schedule-creation time; `recipients` is a plain list of email addresses
 * (spec's "recipient list" is not scoped to existing app users, e.g. it may
 * include an external Finance distribution address).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('report_type')->default('employee');
            $table->json('config');
            $table->json('recipients');
            $table->enum('format', ['csv', 'pdf'])->default('csv');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
