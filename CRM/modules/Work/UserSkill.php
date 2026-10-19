<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserSkill extends Model
{
    protected $fillable = ['user_id', 'skill_id', 'level', 'certified', 'cert_expires_on'];

    protected $casts = ['certified' => 'boolean', 'cert_expires_on' => 'date'];

    public static function levels(): array
    {
        return [1 => 'Learning', 2 => 'Okay solo', 3 => 'Solid', 4 => 'Go-to person', 5 => 'Teaches others'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}
