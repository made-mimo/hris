<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PulseSurveyRunResource;
use App\Models\PulseSurveyRun;
use App\Services\PulseSurveyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Spec F6: "pulse surveys (Section F8)." Read side lists only runs open and targeted at the caller who hasn't yet responded — the same query ⚡pulse-survey-responder.blade.php uses — never the aggregate results, which stay an Admin/HR Admin-only web screen. */
class PulseSurveyController extends ApiController
{
    public function index(Request $request, PulseSurveyService $pulseSurveys)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $openRuns = PulseSurveyRun::with(['template', 'questions'])
            ->where('status', 'open')
            ->get()
            ->filter(fn (PulseSurveyRun $run) => $pulseSurveys->audienceEmployees($run)->contains('id', $employee->id)
                && ! $pulseSurveys->hasResponded($run, $employee));

        return $this->success(PulseSurveyRunResource::collection($openRuns->values()));
    }

    public function respond(Request $request, PulseSurveyRun $pulseSurveyRun, PulseSurveyService $pulseSurveys)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $questions = $pulseSurveyRun->questions()->get()->keyBy('id');

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', Rule::in($questions->keys())],
            'answers.*.scale_value' => [
                'nullable', 'integer',
                function ($attribute, $value, $fail) use ($request, $questions) {
                    $index = (int) explode('.', $attribute)[1];
                    $question = $questions->get($request->input("answers.{$index}.question_id"));
                    if ($question?->type === 'scale' && ($value < $question->scaleMin() || $value > $question->scaleMax())) {
                        $fail("The score must be between {$question->scaleMin()} and {$question->scaleMax()} for this question.");
                    }
                },
            ],
            'answers.*.free_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $answers = collect($data['answers'])->mapWithKeys(fn ($a) => [
            $a['question_id'] => ['scale_value' => $a['scale_value'] ?? null, 'free_text' => $a['free_text'] ?? null],
        ])->all();

        $missingScale = $questions->filter(fn ($q) => $q->type === 'scale' && ($answers[$q->id]['scale_value'] ?? null) === null);
        abort_if($missingScale->isNotEmpty(), 422, 'Every scale question needs a score before you can submit.');

        $pulseSurveys->respond($pulseSurveyRun, $employee, $answers);

        return $this->success(['responded' => true], status: 201);
    }
}
