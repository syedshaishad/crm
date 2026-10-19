<?php

namespace Modules\Projects;

use Illuminate\Database\Eloquent\Model;
use Modules\Tasks\Task;

class Incident extends Model
{
    protected $fillable = ['project_id', 'task_id', 'title', 'severity', 'what_happened', 'root_cause', 'fix', 'status', 'occurred_on', 'created_by'];

    protected $casts = ['occurred_on' => 'date'];

    public static function severities(): array
    {
        return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
