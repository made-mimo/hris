<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Spec Section 3.2's `model=default|detailed` convention: a lean shape by
 * default (list views don't pay for joined data they won't render), a
 * richer one on request (?model=detailed) — one resource class, not two
 * near-duplicate endpoints.
 */
class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'stage' => $this->stageLabel(),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days' => (float) $this->days,
        ];

        if ($request->query('model') === 'detailed') {
            $data['leave_type'] = $this->leaveType?->name;
            $data['reason'] = $this->reason;
            $data['employee'] = [
                'id' => $this->employee_id,
                'name' => $this->employee?->fullName(),
                'employee_id' => $this->employee?->employee_id,
            ];
            $data['manager_approved_at'] = $this->manager_approved_at?->toIso8601String();
            $data['hr_approved_at'] = $this->hr_approved_at?->toIso8601String();
            $data['rejection_reason'] = $this->rejection_reason;
        }

        return $data;
    }
}
