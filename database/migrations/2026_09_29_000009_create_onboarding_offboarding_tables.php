<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B3: reusable, ordered onboarding/offboarding checklist templates whose items carry a day-offset (may be negative) from the employee's join/termination date, plus the per-employee tasks generated from them (or created ad hoc). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_offboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['onboarding', 'offboarding']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('onboarding_offboarding_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('onboarding_offboarding_templates')->cascadeOnDelete();
            $table->string('title');
            $table->integer('offset_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('onboarding_offboarding_template_items')->nullOnDelete();
            $table->enum('kind', ['onboarding', 'offboarding']);
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->date('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tasks');
        Schema::dropIfExists('onboarding_offboarding_template_items');
        Schema::dropIfExists('onboarding_offboarding_templates');
    }
};
