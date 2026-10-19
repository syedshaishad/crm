<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = ['user_id', 'day', 'label', 'note'];

    protected $casts = ['day' => 'date'];

    public static function labels(): array
    {
        return ['Morning (9–6)' => 'Morning (9–6)', 'Evening (2–10)' => 'Evening (2–10)', 'Night (10–6)' => 'Night (10–6)', 'Week off' => 'Week off', 'On call' => 'On call'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
