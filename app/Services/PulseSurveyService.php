<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Location;
use App\Models\PulseSurveyParticipation;
use App\Models\PulseSurveyResponse;
use App\Models\PulseSurveyRun;
use App\Models\PulseSurveyTemplate;
use App\Models\Setting;
use App\Models\SubUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Spec F8: a lightweight, recurring, largely-anonymous engagement survey
 * tool. Reuses the anonymization pattern already established for 360°
 * Feedback (D2): a minimum-N threshold before any breakdown is shown, and
 * eNPS-style scoring computed automatically when the primary question is
 * the standard 0-10 scale.
 */
class PulseSurveyService
{
    /** Naive keyword blocklist — "a lightweight profanity/abuse filter," not a full moderation service. */
    private const BLOCKED_WORDS = ['damn', 'hell', 'stupid', 'idiot'];

    public function __construct(private NotificationService $notifications) {}

    public function launchRun(
        PulseSurveyTemplate $template,
        Carbon $launchDate,
        Carbon $closeDate,
        string $audienceScope,
        ?SubUnit $subUnit,
        ?Location $location,
        bool $isRecurring,
        ?int $recurrenceMonths,
        ?PulseSurveyRun $parent = null,
    ): PulseSurveyRun {
        return DB::transaction(function () use ($template, $launchDate, $closeDate, $audienceScope, $subUnit, $location, $isRecurring, $recurrenceMonths, $parent) {
            $run = PulseSurveyRun::create([
                'pulse_survey_template_id' => $template->id,
                'launch_date' => $launchDate,
                'close_date' => $closeDate,
                'audience_scope' => $audienceScope,
                'audience_sub_unit_id' => $subUnit?->id,
                'audience_location_id' => $location?->id,
                'status' => $launchDate->lte(now()) ? 'open' : 'scheduled',
                'is_recurring' => $isRecurring,
                'recurrence_months' => $recurrenceMonths,
                'parent_run_id' => $parent?->id,
            ]);

            foreach ($this->audienceEmployees($run) as $employee) {
                PulseSurveyParticipation::firstOrCreate(['pulse_survey_run_id' => $run->id, 'employee_id' => $employee->id]);
            }

            if ($run->status === 'open') {
                $this->notifyAudience($run, 'Pulse survey open', "\"{$template->name}\" is now open — your feedback is anonymous and takes a minute.");
            }

            return $run;
        });
    }

    /** @return Collection<int, Employee> */
    public function audienceEmployees(PulseSurveyRun $run): Collection
    {
        return Employee::where('is_gdpr_purged', false)
            ->whereDoesntHave('terminations')
            ->when($run->audience_scope === 'department', fn ($q) => $q->where('sub_unit_id', $run->audience_sub_unit_id))
            ->when($run->audience_scope === 'location', fn ($q) => $q->where('location_id', $run->audience_location_id))
            ->get();
    }

    public function hasResponded(PulseSurveyRun $run, Employee $employee): bool
    {
        return PulseSurveyParticipation::where('pulse_survey_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->where('has_responded', true)
            ->exists();
    }

    /** @return Collection<int, Employee> employees invited to this run who have not yet responded — needed for reminders, never linked to what anyone answered. */
    public function nonRespondents(PulseSurveyRun $run): Collection
    {
        $respondedIds = PulseSurveyParticipation::where('pulse_survey_run_id', $run->id)
            ->where('has_responded', true)
            ->pluck('employee_id');

        return $this->audienceEmployees($run)->reject(fn (Employee $e) => $respondedIds->contains($e->id))->values();
    }

    public function respond(PulseSurveyRun $run, Employee $employee, int $scaleValue, ?string $freeText): PulseSurveyResponse
    {
        abort_if($run->status !== 'open', 422, 'This survey is not currently open.');
        abort_if($this->hasResponded($run, $employee), 422, 'You have already responded to this survey.');

        // Security fix: the web/API responder screens only ever list runs
        // the caller is actually targeted by, but this method itself never
        // re-checked that — any employee who knew (or guessed) another
        // department-scoped run's id could respond to it directly, e.g. via
        // the API, polluting that department's aggregate with an outsider's
        // score.
        abort_unless($this->audienceEmployees($run)->contains('id', $employee->id), 403, 'You are not part of the audience for this survey.');

        return DB::transaction(function () use ($run, $employee, $scaleValue, $freeText) {
            $response = PulseSurveyResponse::create([
                'pulse_survey_run_id' => $run->id,
                'scale_value' => $scaleValue,
                'free_text' => $freeText,
                'is_flagged' => $freeText ? $this->isFlagged($freeText) : false,
            ]);

            PulseSurveyParticipation::updateOrCreate(
                ['pulse_survey_run_id' => $run->id, 'employee_id' => $employee->id],
                ['has_responded' => true]
            );

            return $response;
        });
    }

    private function isFlagged(string $text): bool
    {
        $lower = strtolower($text);

        foreach (self::BLOCKED_WORDS as $word) {
            if (str_contains($lower, $word)) {
                return true;
            }
        }

        return false;
    }

    public function sendReminders(PulseSurveyRun $run): void
    {
        $this->notifyEmployees($this->nonRespondents($run), $run, 'Reminder: pulse survey still open', "\"{$run->template->name}\" closes {$run->close_date->format('j M')} — your anonymous response hasn't been recorded yet.");
    }

    private function notifyAudience(PulseSurveyRun $run, string $title, string $body): void
    {
        $this->notifyEmployees($this->audienceEmployees($run), $run, $title, $body);
    }

    private function notifyEmployees(Collection $employees, PulseSurveyRun $run, string $title, string $body): void
    {
        foreach ($employees as $employee) {
            if ($employee->user) {
                $this->notifications->notify($employee->user, 'pulse_survey', $title, $body, null, 'Pulse Survey');
            }
        }
    }

    /**
     * Spec F8: "a minimum-N threshold before any breakdown is shown, so no
     * individual can be inferred from a small group's aggregate."
     *
     * @return array{sufficient: bool, count: int, average?: float, enps?: int, freeText?: Collection}
     */
    public function aggregatedResults(PulseSurveyRun $run): array
    {
        $minResponses = Setting::current()->pulse_survey_min_responses;
        // Query fresh rather than the cached `responses` relation property —
        // this method is often called right after new responses are
        // inserted on the same $run instance within one request.
        $responses = $run->responses()->get();
        $count = $responses->count();

        if ($count < $minResponses) {
            return ['sufficient' => false, 'count' => $count, 'minRequired' => $minResponses];
        }

        $result = [
            'sufficient' => true,
            'count' => $count,
            'average' => round($responses->avg('scale_value'), 2),
            'freeText' => $responses->whereNotNull('free_text')->where('is_flagged', false)->pluck('free_text')->values(),
            'flaggedCount' => $responses->where('is_flagged', true)->count(),
        ];

        if ($run->template->scale_type === 'enps_0_10') {
            $promoters = $responses->where('scale_value', '>=', 9)->count();
            $detractors = $responses->where('scale_value', '<=', 6)->count();
            $result['enps'] = (int) round((($promoters / $count) - ($detractors / $count)) * 100);
        }

        return $result;
    }

    /** Spec F8: "if a run's close, spawn the next recurring instance automatically." Idempotent via parent_run_id — never spawns a second child. */
    public function closeRunAndSpawnNext(PulseSurveyRun $run): ?PulseSurveyRun
    {
        $run->update(['status' => 'closed']);

        if (! $run->is_recurring) {
            return null;
        }

        $alreadySpawned = PulseSurveyRun::where('parent_run_id', $run->id)->exists();
        if ($alreadySpawned) {
            return null;
        }

        $openDays = $run->launch_date->diffInDays($run->close_date);
        $nextLaunch = $run->launch_date->copy()->addMonths($run->recurrence_months);

        return $this->launchRun(
            $run->template,
            $nextLaunch,
            $nextLaunch->copy()->addDays($openDays),
            $run->audience_scope,
            $run->audienceSubUnit,
            $run->audienceLocation,
            true,
            $run->recurrence_months,
            $run,
        );
    }
}
