<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class Suggestion extends Model
{
    protected $fillable = ['body', 'mood', 'status'];

    public static function moods(): array
    {
        return ['idea' => 'Idea', 'praise' => 'Praise', 'gripe' => 'Gripe', 'question' => 'Question'];
    }
}
