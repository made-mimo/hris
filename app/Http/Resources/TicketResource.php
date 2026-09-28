<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'subject' => $this->subject,
            'category' => $this->category?->name,
            'status' => $this->status,
            'raised_by' => $this->raiserLabel(),
            'assigned_to' => $this->assignedTo?->fullName(),
            'created_at' => $this->created_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];

        if ($request->query('model') === 'detailed') {
            $data['description'] = $this->description;
            $data['comments'] = $this->comments->map(fn ($c) => [
                'id' => $c->id,
                'author' => $c->author->fullName(),
                'body' => $c->body,
                'created_at' => $c->created_at->toIso8601String(),
            ]);
        }

        return $data;
    }
}
