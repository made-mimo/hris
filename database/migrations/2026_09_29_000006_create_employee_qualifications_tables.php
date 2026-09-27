<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B2 Qualifications tab: education history, skills, languages, licenses, professional memberships, work experience — five child tables plus one pivot-like table (skills), each referencing the relevant B1 master list where one exists. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('institution');
            $table->string('qualification');
            $table->foreignId('education_level_id')->nullable()->constrained('master_list_items')->nullOnDelete();
            $table->string('field_of_study')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('master_list_items')->cascadeOnDelete();
            $table->decimal('years_experience', 4, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'skill_id']);
        });

        Schema::create('employee_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('master_list_items')->cascadeOnDelete();
            $table->string('reading_level')->default('basic');
            $table->string('writing_level')->default('basic');
            $table->string('speaking_level')->default('basic');
            $table->timestamps();

            $table->unique(['employee_id', 'language_id']);
        });

        Schema::create('employee_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_type_id')->constrained('master_list_items')->cascadeOnDelete();
            $table->string('license_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_body_id')->constrained('master_list_items')->cascadeOnDelete();
            $table->decimal('subscription_fee', 12, 2)->nullable();
            $table->date('renewal_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_work_experience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employer');
            $table->string('job_title');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_experience');
        Schema::dropIfExists('employee_memberships');
        Schema::dropIfExists('employee_licenses');
        Schema::dropIfExists('employee_languages');
        Schema::dropIfExists('employee_skills');
        Schema::dropIfExists('employee_education');
    }
};
