<?php

namespace Modules\Tasks;

use Illuminate\Database\Eloquent\Model;

class TaskChecklist extends Model
{
    protected $fillable = ['task_id', 'label', 'is_done', 'position'];

    protected $casts = ['is_done' => 'boolean'];
}
