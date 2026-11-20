<?php

namespace Modules\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'path', 'mime', 'size', 'linkable_type', 'linkable_id',
        'uploaded_by', 'sign_token', 'signed_by', 'signed_at', 'signature_path'];

    protected $casts = ['signed_at' => 'datetime'];

    public function linkable()
    {
        return $this->morphTo();
    }
}
