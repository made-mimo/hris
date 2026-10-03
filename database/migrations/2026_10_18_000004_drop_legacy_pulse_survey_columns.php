<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Backlog #11 — the fixed columns these replace are fully migrated into pulse_survey_questions/pulse_survey_answers by the previous migration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pulse_survey_templates', function (Blueprint $table) {
            $table->dropColumn(['primary_question', 'scale_type', 'free_text_question']);
        });

        Schema::table('pulse_survey_responses', function (Blueprint $table) {
            $table->dropColumn(['scale_value', 'free_text', 'is_flagged']);
        });
    }

    public function down(): void
    {
        Schema::table('pulse_survey_templates', function (Blueprint $table) {
            $table->text('primary_question')->nullable();
            $table->string('scale_type')->nullable();
            $table->text('free_text_question')->nullable();
        });

        Schema::table('pulse_survey_responses', function (Blueprint $table) {
            $table->unsignedTinyInteger('scale_value')->nullable();
            $table->text('free_text')->nullable();
            $table->boolean('is_flagged')->default(false);
        });
    }
};
