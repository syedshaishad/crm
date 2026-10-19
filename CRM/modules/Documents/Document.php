<?php

namespace Modules\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'path', 'mime', 'size', 'linkable_type', 'linkable_id', 'uploaded_by'];

    public function linkable()
    {
        return $this->morphTo();
    }
}
