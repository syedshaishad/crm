<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    protected $fillable = ['user_id', 'effective_from', 'basic', 'hra', 'allowances', 'is_active'];

    protected $casts = ['effective_from' => 'date', 'is_active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gross(): float
    {
        return round((float) $this->basic + (float) $this->hra + (float) $this->allowances, 2);
    }

    /** Active structure for a user on a given date (latest effective wins). */
    public static function for(int $userId, string $on): ?self
    {
        return self::where('user_id', $userId)->where('is_active', true)
            ->whereDate('effective_from', '<=', $on)->orderByDesc('effective_from')->first();
    }
}
