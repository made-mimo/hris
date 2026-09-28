<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec D3 Employee Relations / Discipline Case Management: a supervisor
 * raises a case against a subordinate, the employee responds, HR resolves
 * or follows up. Attachments go through Media Library on each model
 * directly (spec 3.5's established pattern — see Vacancy/Candidate/
 * Interview in D1) rather than bespoke attachment tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('employees')->cascadeOnDelete();
            $table->string('case_type');
            $table->enum('severity', ['low', 'medium', 'high']);
            $table->text('description');
            $table->date('incident_date');
            $table->string('status')->default('open');
            $table->string('outcome')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });

        Schema::create('case_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['response', 'follow_up']);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_responses');
        Schema::dropIfExists('disciplinary_cases');
    }
};
