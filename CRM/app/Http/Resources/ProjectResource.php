<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'client' => $this->whenLoaded('client', fn () => $this->client->company ?: $this->client->name),
            'client_id' => $this->client_id,
            'status' => $this->status,
            'budget' => (float) $this->budget,
            'start_date' => $this->start_date,
            'due_date' => $this->due_date,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
