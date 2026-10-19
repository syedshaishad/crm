<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'gstin' => $this->gstin,
            'state' => $this->state,
            'status' => $this->status,
            'notes' => $this->notes,
            'billed' => (float) ($this->billed ?? $this->invoices->sum('total') ?? 0),
            'collected' => (float) ($this->collected ?? $this->invoices->sum('paid_amount') ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
