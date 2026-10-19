<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'category' => $this->category,
            'vendor' => $this->vendor,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'spent_on' => $this->spent_on,
            'project' => $this->whenLoaded('project', fn () => $this->project?->name),
            'project_id' => $this->project_id,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
