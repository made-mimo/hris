<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Backlog #11 — which of a template's question-bank questions a given run actually asks, in that run's own order. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulse_survey_run_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulse_survey_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pulse_survey_question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pulse_survey_run_id', 'pulse_survey_question_id'], 'run_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_survey_run_question');
    }
};
