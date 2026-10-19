<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'client' => $this->whenLoaded('client', fn () => $this->client->company ?: $this->client->name),
            'client_id' => $this->client_id,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice?->number),
            'invoice_id' => $this->invoice_id,
            'method' => $this->method,
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'paid_on' => $this->paid_on,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
