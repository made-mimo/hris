<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FeedbackCycle;
use App\Models\FeedbackParticipant;
use App\Models\Kpi;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec D2's business rules that sit above plain CRUD: review activation
 * requires at least one active KPI for the employee's job title and
 * auto-creates both reviewer tracks with rating stubs; final evaluation
 * force-completes both tracks; 360° cycle initiation is restricted to the
 * subject's manager or HR/Admin.
 */
class PerformanceService
{
    public function activate(PerformanceReview $review): void
    {
        abort_if($review->status !== 'inactive', 422, 'This review has already been activated.');

        $kpis = Kpi::applicableTo($review->job_title_id);

        if ($kpis->isEmpty()) {
            throw ValidationException::withMessages(['activate' => "This employee's job title has no active KPIs configured — add at least one before activating a review."]);
        }

        DB::transaction(function () use ($review, $kpis) {
            $employee = $review->employee;

            $groups = ['self' => $employee->id];
            if ($employee->supervisor_id) {
                $groups['supervisor'] = $employee->supervisor_id;
            }

            foreach ($groups as $group => $reviewerEmployeeId) {
                $reviewer = PerformanceReviewer::create([
                    'performance_review_id' => $review->id,
                    'employee_id' => $reviewerEmployeeId,
                    'group' => $group,
                    'status' => 'pending',
                ]);

                foreach ($kpis as $kpi) {
                    $reviewer->ratings()->create(['kpi_id' => $kpi->id]);
                }
            }

            $review->update(['status' => 'activated', 'activated_at' => now()]);
        });
    }

    public function saveRatings(PerformanceReviewer $reviewer, array $ratings, ?string $note = null): void
    {
        abort_if($reviewer->status === 'completed', 422, 'This reviewer track is already complete and signed off.');

        foreach ($ratings as $kpiId => $rating) {
            $reviewer->ratings()->where('kpi_id', $kpiId)->update(['rating' => $rating]);
        }

        if ($reviewer->status === 'pending') {
            $reviewer->update(['status' => 'in_progress']);
        }

        if ($reviewer->review->status === 'activated') {
            $reviewer->review->update(['status' => 'in_progress']);
        }
    }

    /** Spec D2: "Reviewer sign-off is a separate, one-time, immutable, timestamped action...through the E-Signature & Digital Consent Service." */
    public function signOff(PerformanceReviewer $reviewer, User $user, SignatureService $signatures): void
    {
        abort_if($reviewer->status === 'completed', 422, 'Already signed off.');
        abort_if($reviewer->ratings()->whereNull('rating')->exists(), 422, 'Every KPI must be rated before signing off.');

        $content = "Performance review #{$reviewer->performance_review_id} — {$reviewer->group} sign-off by employee #{$reviewer->employee_id}.";

        $signatures->sign(
            signable: $reviewer,
            signer: $user,
            purpose: 'performance_reviewer_signoff',
            content: $content,
            method: 'click_to_sign',
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        $reviewer->update(['status' => 'completed', 'completed_at' => now()]);
    }

    /** Spec D2: "Final evaluation sets the overall 0–100 rating and completion date, and force-completes all reviewer sub-records." */
    public function finalize(PerformanceReview $review, float $finalRating, ?string $finalComment): void
    {
        DB::transaction(function () use ($review, $finalRating, $finalComment) {
            $review->reviewers()->where('status', '!=', 'completed')->get()->each(
                fn (PerformanceReviewer $r) => $r->update(['status' => 'completed', 'completed_at' => now()])
            );

            $review->update([
                'status' => 'completed',
                'final_rating' => $finalRating,
                'final_comment' => $finalComment,
                'completed_at' => now(),
            ]);
        });
    }

    /** Spec D2: "only a subject's manager (or HR/Admin) may initiate a cycle." */
    public function canInitiateCycle(User $user, Employee $subject): bool
    {
        if ($user->isAdmin() || $user->isHr()) {
            return true;
        }

        return $user->employee && (int) $subject->supervisor_id === $user->employee->id;
    }

    /**
     * @param  array<int, array{employeeId: int, relationship: string}>  $participants
     */
    public function initiateCycle(int $templateId, Employee $subject, User $user, string $dueDate, bool $sharedWithSubject, array $participants): FeedbackCycle
    {
        abort_unless($this->canInitiateCycle($user, $subject), 403, "Only this employee's manager or HR may initiate 360° feedback.");

        return DB::transaction(function () use ($templateId, $subject, $user, $dueDate, $sharedWithSubject, $participants) {
            $cycle = FeedbackCycle::create([
                'feedback_template_id' => $templateId,
                'subject_employee_id' => $subject->id,
                'initiated_by' => $user->id,
                'due_date' => $dueDate,
                'shared_with_subject' => $sharedWithSubject,
                'status' => 'open',
            ]);

            foreach ($participants as $p) {
                FeedbackParticipant::create([
                    'feedback_cycle_id' => $cycle->id,
                    'rater_employee_id' => $p['employeeId'],
                    'relationship_tag' => $p['relationship'],
                    'status' => 'pending',
                ]);
            }

            return $cycle;
        });
    }

    public function submitFeedback(FeedbackParticipant $participant, array $responses): void
    {
        abort_if($participant->status === 'completed', 422, 'You have already submitted this feedback.');

        foreach ($responses as $questionId => $data) {
            $participant->responses()->updateOrCreate(
                ['feedback_template_question_id' => $questionId],
                ['rating' => $data['rating'] ?? null, 'comment' => $data['comment'] ?? null]
            );
        }

        $participant->update(['status' => 'completed']);

        $cycle = $participant->cycle;
        if (! $cycle->participants()->where('status', '!=', 'completed')->exists()) {
            $cycle->update(['status' => 'closed']);
        }
    }

    /**
     * Spec D2: "peer and direct-report responses are pooled into an
     * anonymized per-question average with unattributed comments...while
     * manager and self responses remain individually attributed."
     *
     * @return array{attributed: Collection, pooled: Collection}
     */
    public function aggregatedResults(FeedbackCycle $cycle): array
    {
        $participants = $cycle->participants()->with(['rater', 'responses.question'])->where('status', 'completed')->get();

        $attributed = $participants->filter->isAttributed()->map(fn (FeedbackParticipant $p) => [
            'relationship' => $p->relationship_tag,
            'rater' => $p->rater->fullName(),
            'responses' => $p->responses,
        ]);

        $pooledParticipants = $participants->reject->isAttributed();
        $questionIds = $pooledParticipants->flatMap(fn ($p) => $p->responses->pluck('feedback_template_question_id'))->unique();

        $pooled = $questionIds->map(function ($questionId) use ($pooledParticipants) {
            $responses = $pooledParticipants->flatMap->responses->where('feedback_template_question_id', $questionId);

            return [
                'question' => $responses->first()?->question,
                'average_rating' => round((float) $responses->avg('rating'), 2),
                'comments' => $responses->pluck('comment')->filter()->values(),
            ];
        });

        return ['attributed' => $attributed, 'pooled' => $pooled];
    }
}
