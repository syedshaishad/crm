<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'rate' => (float) $this->rate,
            'tax_rate' => $this->tax_rate !== null ? (float) $this->tax_rate : null,
            'hsn_code' => $this->hsn_code,
            'unit' => $this->unit,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
