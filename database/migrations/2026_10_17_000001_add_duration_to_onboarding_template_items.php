<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin backlog item 6b/6c — a task's effort (how long it takes) is a
 * separate concept from offset_days (when it's due, relative to join/
 * termination date) — that field stays untouched. duration_unit lets a task
 * be estimated in hours or whole days; the template's own cumulative time
 * (App\Models\OnboardingOffboardingTemplate::cumulativeHours()) converts
 * everything to hours using WORKING_HOURS_PER_DAY, so the two units can be
 * summed meaningfully.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_offboarding_template_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_value')->default(0)->after('offset_days');
            $table->string('duration_unit')->default('hours')->after('duration_value');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_offboarding_template_items', function (Blueprint $table) {
            $table->dropColumn(['duration_value', 'duration_unit']);
        });
    }
};
