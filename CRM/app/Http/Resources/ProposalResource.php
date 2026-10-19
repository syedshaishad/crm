<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'client' => $this->whenLoaded('client', fn () => $this->client->company ?: $this->client->name),
            'client_id' => $this->client_id,
            'subject' => $this->subject,
            'status' => $this->status,
            'total' => (float) $this->total,
            'valid_until' => $this->valid_until,
            'signed_by' => $this->signed_by,
            'signed_at' => $this->signed_at,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
