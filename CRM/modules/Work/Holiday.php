<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['day', 'name'];

    protected $casts = ['day' => 'date'];
}
