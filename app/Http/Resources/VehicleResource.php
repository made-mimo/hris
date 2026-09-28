<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'registration_number' => $this->registration_number,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
        ];

        if ($request->query('model') === 'detailed') {
            $data['color'] = $this->color;
            $data['notes'] = $this->notes;
            $data['renewals'] = $this->renewals->map(fn ($r) => [
                'label' => $r->label,
                'expiry_date' => $r->expiry_date->toDateString(),
            ]);
        }

        return $data;
    }
}
