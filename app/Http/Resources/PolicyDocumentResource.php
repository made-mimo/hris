<?php

namespace App\Http\Resources;

use App\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion();
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category?->name,
            'current_version' => $version?->version_label,
            'effective_date' => $version?->effective_date?->toDateString(),
            'acknowledged' => $version && $user
                ? $version->isAcknowledgedBy($user, app(SignatureService::class))
                : null,
        ];
    }
}
