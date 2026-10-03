<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PulseSurveyRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_name' => $this->template->name,
            'status' => $this->status,
            'close_date' => $this->close_date->toDateString(),
            'audience' => $this->audienceLabel(),
            'questions' => $this->questions->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'prompt' => $q->prompt,
                'scale_type' => $q->scale_type,
                'scale_min' => $q->type === 'scale' ? $q->scaleMin() : null,
                'scale_max' => $q->type === 'scale' ? $q->scaleMax() : null,
            ]),
        ];
    }
}
