<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'stage' => $this->stageLabel(),
            'currency' => $this->currency,
            'total' => $this->total(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];

        if ($request->query('model') === 'detailed') {
            $data['employee'] = [
                'id' => $this->employee_id,
                'name' => $this->employee?->fullName(),
            ];
            $data['net'] = $this->net();
            $data['requires_second_approval'] = $this->requiresSecondApproval();
            $data['reconciliation_variance'] = $this->reconciliationVariance();
            $data['rejection_reason'] = $this->rejection_reason;
            $data['payment_method'] = $this->payment_method;
            $data['payment_reference'] = $this->payment_reference;
            $data['paid_at'] = $this->paid_at?->toIso8601String();
            $data['lines'] = $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'type_id' => $line->expense_type_id,
                'date' => $line->date->toDateString(),
                'note' => $line->note,
                'amount' => (float) $line->amount,
                'flagged' => $line->flagged,
            ]);
        }

        return $data;
    }
}
