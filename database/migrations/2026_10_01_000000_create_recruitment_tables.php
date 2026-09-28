<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec D1 Recruitment: Requisitions (optional pre-vacancy approval gate) →
 * Vacancies → Candidates → CandidateApplications (the candidate/vacancy pipeline,
 * carrying the pipeline status — a full model, not a bare pivot, since it
 * has its own workflow state and history) → Interviews (one-to-many
 * interviewers via a pivot) → CandidateHistories (append-only audit trail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete();
            $table->unsignedInteger('position_count');
            $table->text('justification');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hiring_manager_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status')->default('requested');
            $table->text('decision_comment')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position_count')->default(1);
            $table->boolean('is_open')->default(true);
            $table->boolean('is_published')->default(false);
            $table->foreignId('hiring_manager_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->enum('application_mode', ['manual', 'online'])->default('manual');
            $table->date('application_date');
            $table->string('keyword_tags')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->timestamps();
        });

        Schema::create('candidate_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->string('status')->default('application_initiated');
            $table->timestamps();

            $table->unique(['candidate_id', 'vacancy_id']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->string('name');
            $table->date('interview_date');
            $table->time('interview_time')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('interview_interviewer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['interview_id', 'employee_id']);
        });

        // Append-only audit trail (spec: "a full audit trail of every
        // pipeline action, who performed it, and any linked interview") —
        // same const UPDATED_AT = null pattern as TimesheetActionLog.
        Schema::create('candidate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->foreignId('interview_id')->nullable()->constrained('interviews')->nullOnDelete();
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_histories');
        Schema::dropIfExists('interview_interviewer');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('candidate_applications');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('vacancies');
        Schema::dropIfExists('requisitions');
    }
};
