<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PulseSurveyRunResource;
use App\Models\PulseSurveyRun;
use App\Services\PulseSurveyService;
use Illuminate\Http\Request;

/** Spec F6: "pulse surveys (Section F8)." Read side lists only runs open and targeted at the caller who hasn't yet responded — the same query ⚡pulse-survey-responder.blade.php uses — never the aggregate results, which stay an Admin/HR Admin-only web screen. */
class PulseSurveyController extends ApiController
{
    public function index(Request $request, PulseSurveyService $pulseSurveys)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $openRuns = PulseSurveyRun::with('template')
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

        $max = $pulseSurveyRun->template->scale_type === 'enps_0_10' ? 10 : 5;

        $data = $request->validate([
            'scale_value' => ['required', 'integer', 'min:0', 'max:'.$max],
            'free_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $pulseSurveys->respond($pulseSurveyRun, $employee, $data['scale_value'], $data['free_text'] ?? null);

        return $this->success(['responded' => true], status: 201);
    }
}
