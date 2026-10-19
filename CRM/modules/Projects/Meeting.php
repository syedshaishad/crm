<?php

namespace Modules\Projects;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = ['title', 'starts_at', 'duration_minutes', 'attendees', 'agenda', 'notes', 'project_id', 'created_by'];

    protected $casts = ['starts_at' => 'datetime', 'attendees' => 'array'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function endsAt(): \Carbon\Carbon
    {
        return $this->starts_at->copy()->addMinutes((int) $this->duration_minutes);
    }
}
