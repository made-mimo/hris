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
            'primary_question' => $this->template->primary_question,
            'free_text_question' => $this->template->free_text_question,
            'scale_type' => $this->template->scale_type,
            'status' => $this->status,
            'close_date' => $this->close_date->toDateString(),
            'audience' => $this->audienceLabel(),
        ];
    }
}
