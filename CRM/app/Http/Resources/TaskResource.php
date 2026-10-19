<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date,
            'project' => $this->whenLoaded('project', fn () => $this->project?->name),
            'project_id' => $this->project_id,
            'assigned_to' => $this->assignee?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
