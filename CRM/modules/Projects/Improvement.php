<?php

namespace Modules\Projects;

use Illuminate\Database\Eloquent\Model;

class Improvement extends Model
{
    protected $fillable = ['title', 'detail', 'status', 'created_by'];

    public static function statuses(): array
    {
        return ['proposed' => 'Proposed', 'doing' => 'Doing', 'done' => 'Done'];
    }
}
