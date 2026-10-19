<?php

namespace Modules\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaskTime extends Model
{
    protected $fillable = ['task_id', 'user_id', 'minutes', 'worked_on', 'note', 'invoice_id'];

    protected $casts = ['worked_on' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function invoice()
    {
        return $this->belongsTo(\Modules\Invoices\Invoice::class);
    }

    public static function fmt(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.'m';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }
}
