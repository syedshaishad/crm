<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Tasks\Task;

class FieldVisit extends Model
{
    protected $fillable = ['user_id', 'task_id', 'check_in_at', 'in_lat', 'in_lng', 'check_out_at', 'out_lat', 'out_lng', 'note'];

    protected $casts = ['check_in_at' => 'datetime', 'check_out_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function minutes(): ?int
    {
        if (! $this->check_out_at) {
            return null;
        }

        return max(1, (int) $this->check_in_at->diffInMinutes($this->check_out_at));
    }

    public function mapsUrl(?string $which = 'in'): ?string
    {
        $lat = $which === 'out' ? $this->out_lat : $this->in_lat;
        $lng = $which === 'out' ? $this->out_lng : $this->in_lng;
        if ($lat === null || $lng === null) {
            return null;
        }

        return 'https://www.google.com/maps?q='.$lat.','.$lng;
    }
}
