<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec D2 Performance Management: job-title-scoped KPIs, a formal cyclical
 * Review with independent Supervisor/Self reviewer tracks and per-reviewer
 * per-KPI ratings, an ongoing lighter-weight Performance Tracker, Goals, and
 * 360-degree multi-rater Feedback (templates → cycles → participants →
 * responses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('min_scale', 5, 2)->default(0);
            $table->decimal('max_scale', 5, 2)->default(5);
            // Null = a default KPI applied to every job title; set = specific
            // to that one job title only (spec: "whether job-title-specific
            // or a default").
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete();
            $table->foreignId('sub_unit_id')->nullable()->constrained('sub_units')->nullOnDelete();
            $table->string('status')->default('inactive');
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->date('due_date')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('final_comment')->nullable();
            $table->decimal('final_rating', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('performance_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('group', ['supervisor', 'self']);
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['performance_review_id', 'employee_id', 'group'], 'perf_reviewer_review_employee_group_unique');
        });

        Schema::create('performance_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_reviewer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['performance_reviewer_id', 'kpi_id']);
        });

        Schema::create('performance_tracker_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('entry_date');
            $table->enum('sentiment', ['positive', 'negative']);
            $table->text('description');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Spec: "assignable reviewers" for a Performance Tracker — who
        // besides the employee's own supervisor may view/log entries on it.
        Schema::create('performance_tracker_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'reviewer_id']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->decimal('current_value', 10, 2)->nullable();
            $table->string('unit')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('not_started');
            $table->foreignId('performance_review_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_record_id')->nullable()->constrained('training_records')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feedback_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('feedback_template_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_template_id')->constrained()->cascadeOnDelete();
            $table->string('question_text');
            $table->decimal('min_scale', 5, 2)->default(1);
            $table->decimal('max_scale', 5, 2)->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('feedback_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->date('due_date');
            $table->boolean('shared_with_subject')->default(false);
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('feedback_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('relationship_tag', ['manager', 'peer', 'direct_report', 'self']);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['feedback_cycle_id', 'rater_employee_id']);
        });

        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_template_question_id')->constrained()->cascadeOnDelete();
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['feedback_participant_id', 'feedback_template_question_id'], 'feedback_response_participant_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_responses');
        Schema::dropIfExists('feedback_participants');
        Schema::dropIfExists('feedback_cycles');
        Schema::dropIfExists('feedback_template_questions');
        Schema::dropIfExists('feedback_templates');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('performance_tracker_reviewers');
        Schema::dropIfExists('performance_tracker_entries');
        Schema::dropIfExists('performance_ratings');
        Schema::dropIfExists('performance_reviewers');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('kpis');
    }
};
