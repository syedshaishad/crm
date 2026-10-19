<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'client' => $this->whenLoaded('client', fn () => $this->client->company ?: $this->client->name),
            'client_id' => $this->client_id,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'gst_rate' => (float) $this->gst_rate,
            'gst_amount' => (float) $this->gst_amount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'balance' => round(max(0, (float) $this->total - (float) $this->paid_amount), 2),
            'place_of_supply' => $this->place_of_supply,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'description' => $i->description, 'hsn_code' => $i->hsn_code, 'unit' => $i->unit,
                'qty' => (float) $i->qty, 'rate' => (float) $i->rate, 'tax_rate' => $i->tax_rate !== null ? (float) $i->tax_rate : null, 'amount' => (float) $i->amount,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
