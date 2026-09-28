<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec F8: "a configurable minimum number of responses (default 5)" before any breakdown is shown — the same anonymization threshold pattern as 360° Feedback (D2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('pulse_survey_min_responses')->default(5);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('pulse_survey_min_responses');
        });
    }
};
