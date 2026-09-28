<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec F8: "participation tracking without identity leakage...two separate,
 * deliberately-unlinked records (a participation flag, and an anonymous
 * response), not one record with a name attached." Responses carry no
 * employee_id, ever — Participations track who has been invited/responded
 * for reminder purposes only, with no link to what (or whether) they
 * actually answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulse_survey_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('primary_question');
            $table->string('scale_type')->comment('likert_5|enps_0_10');
            $table->text('free_text_question')->nullable();
            $table->timestamps();
        });

        Schema::create('pulse_survey_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulse_survey_template_id')->constrained()->cascadeOnDelete();
            $table->date('launch_date');
            $table->date('close_date');
            $table->string('audience_scope')->default('all')->comment('all|department|location');
            $table->foreignId('audience_sub_unit_id')->nullable()->constrained('sub_units')->cascadeOnDelete();
            $table->foreignId('audience_location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->string('status')->default('scheduled')->comment('scheduled|open|closed');
            $table->boolean('is_recurring')->default(false);
            $table->unsignedSmallInteger('recurrence_months')->nullable();
            $table->foreignId('parent_run_id')->nullable()->constrained('pulse_survey_runs')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pulse_survey_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulse_survey_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->boolean('has_responded')->default(false);
            $table->timestamps();

            $table->unique(['pulse_survey_run_id', 'employee_id'], 'pulse_participation_unique');
        });

        // Deliberately no employee_id column — see class doc comment above.
        Schema::create('pulse_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulse_survey_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('scale_value');
            $table->text('free_text')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_survey_responses');
        Schema::dropIfExists('pulse_survey_participations');
        Schema::dropIfExists('pulse_survey_runs');
        Schema::dropIfExists('pulse_survey_templates');
    }
};
