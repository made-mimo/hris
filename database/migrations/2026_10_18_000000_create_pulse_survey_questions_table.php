<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backlog #11 — a template becomes a reusable question bank instead of a
 * hard-coded pair of fields, so HR Admin can author more than one scale/
 * free-text question per template and a run can select a subset of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulse_survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulse_survey_template_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('prompt');
            $table->string('scale_type')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_survey_questions');
    }
};
