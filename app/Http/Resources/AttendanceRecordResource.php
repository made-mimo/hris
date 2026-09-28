<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'punch_in_at' => $this->punch_in_at_local->toIso8601String(),
            'punch_out_at' => $this->punch_out_at_local?->toIso8601String(),
            'duration_hours' => $this->durationHours(),
            'is_open' => $this->isOpen(),
            'is_proxy_punch' => $this->is_proxy_punch,
        ];
    }
}
