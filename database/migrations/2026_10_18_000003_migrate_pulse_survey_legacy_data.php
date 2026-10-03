<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backlog #11 data migration — every existing template had exactly one
 * scale question (primary_question/scale_type) plus an optional free-text
 * question (free_text_question); every existing run asked both of a
 * template's (then-only) questions; every existing response answered both.
 * This backfills the new question-bank/run-selection/per-question-answer
 * tables from that fixed shape before the legacy columns are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (DB::table('pulse_survey_templates')->get() as $template) {
            $scaleQuestionId = DB::table('pulse_survey_questions')->insertGetId([
                'pulse_survey_template_id' => $template->id,
                'type' => 'scale',
                'prompt' => $template->primary_question,
                'scale_type' => $template->scale_type,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $freeTextQuestionId = null;
            if (! empty($template->free_text_question)) {
                $freeTextQuestionId = DB::table('pulse_survey_questions')->insertGetId([
                    'pulse_survey_template_id' => $template->id,
                    'type' => 'free_text',
                    'prompt' => $template->free_text_question,
                    'scale_type' => null,
                    'sort_order' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $runs = DB::table('pulse_survey_runs')->where('pulse_survey_template_id', $template->id)->get();

            foreach ($runs as $run) {
                DB::table('pulse_survey_run_question')->insert([
                    'pulse_survey_run_id' => $run->id,
                    'pulse_survey_question_id' => $scaleQuestionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($freeTextQuestionId) {
                    DB::table('pulse_survey_run_question')->insert([
                        'pulse_survey_run_id' => $run->id,
                        'pulse_survey_question_id' => $freeTextQuestionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach (DB::table('pulse_survey_responses')->where('pulse_survey_run_id', $run->id)->get() as $response) {
                    DB::table('pulse_survey_answers')->insert([
                        'pulse_survey_response_id' => $response->id,
                        'pulse_survey_question_id' => $scaleQuestionId,
                        'scale_value' => $response->scale_value,
                        'free_text' => null,
                        'is_flagged' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($freeTextQuestionId && $response->free_text !== null) {
                        DB::table('pulse_survey_answers')->insert([
                            'pulse_survey_response_id' => $response->id,
                            'pulse_survey_question_id' => $freeTextQuestionId,
                            'scale_value' => null,
                            'free_text' => $response->free_text,
                            'is_flagged' => $response->is_flagged,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('pulse_survey_answers')->truncate();
        DB::table('pulse_survey_run_question')->truncate();
        DB::table('pulse_survey_questions')->truncate();
    }
};
