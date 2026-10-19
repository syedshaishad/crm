<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['name'];

    public function holders()
    {
        return $this->hasMany(UserSkill::class);
    }
}
