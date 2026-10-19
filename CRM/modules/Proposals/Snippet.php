<?php

namespace Modules\Proposals;

use Illuminate\Database\Eloquent\Model;

class Snippet extends Model
{
    protected $fillable = ['title', 'kind', 'body'];

    public static function kinds(): array
    {
        return ['scope' => 'Scope of work', 'results' => 'Expected results', 'terms' => 'Terms', 'notes' => 'Notes'];
    }
}
