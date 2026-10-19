<?php

namespace Modules\Tasks;

use Illuminate\Database\Eloquent\Model;

class TaskDependency extends Model
{
    protected $fillable = ['task_id', 'depends_on_task_id'];

    public function blocker()
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}
