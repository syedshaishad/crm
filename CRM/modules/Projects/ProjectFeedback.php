<?php

namespace Modules\Projects;

use Illuminate\Database\Eloquent\Model;

class ProjectFeedback extends Model
{
    protected $table = 'project_feedback';

    protected $fillable = ['project_id', 'score', 'comment', 'author'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
