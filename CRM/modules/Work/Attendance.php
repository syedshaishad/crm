<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = ['user_id', 'day', 'status', 'note'];

    protected $casts = ['day' => 'date'];

    public static function statuses(): array
    {
        return ['present' => 'Present', 'half' => 'Half day', 'leave' => 'Leave', 'absent' => 'Absent'];
    }

    /**
     * Idempotent daily mark. Compares by calendar date (SQLite stores the
     * datetime form), so re-marking a day updates instead of crashing.
     */
    public static function mark(int $userId, string $day, string $status, ?string $note = null): self
    {
        $day = substr($day, 0, 10);
        $row = self::where('user_id', $userId)->whereDate('day', $day)->first();
        if ($row) {
            $row->update(['status' => $status, 'note' => $note]);

            return $row->fresh();
        }

        return self::create(['user_id' => $userId, 'day' => $day, 'status' => $status, 'note' => $note]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
