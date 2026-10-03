<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backlog #11 — one row per (anonymous response, question) pair, replacing
 * the single scale_value/free_text columns pulse_survey_responses used to
 * carry directly. The response row stays the anonymous "submission
 * envelope" (still no employee_id anywhere); this table just lets that
 * envelope hold N answers instead of exactly one scale + one free-text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulse_survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulse_survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pulse_survey_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('scale_value')->nullable();
            $table->text('free_text')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_survey_answers');
    }
};
