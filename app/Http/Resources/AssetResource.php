<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'tag' => $this->tag,
            'name' => $this->name,
            'category' => $this->category?->name,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
        ];

        if ($request->query('model') === 'detailed') {
            $data['serial_number'] = $this->serial_number;
            $data['notes'] = $this->notes;
            $data['warranties'] = $this->warranties->map(fn ($w) => [
                'provider' => $w->provider,
                'expiry_date' => $w->expiry_date->toDateString(),
            ]);
        }

        return $data;
    }
}
