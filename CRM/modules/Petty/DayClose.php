<?php

namespace Modules\Petty;

use Illuminate\Database\Eloquent\Model;

class DayClose extends Model
{
    protected $fillable = ['day', 'cash_counted', 'expected_petty', 'cash_receipts', 'checks', 'notes', 'closed_by'];

    protected $casts = ['day' => 'date', 'checks' => 'array'];

    public function closer()
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by');
    }

    public static function checklist(): array
    {
        return [
            'cash' => 'Cash drawer tallied with petty book',
            'invoices' => "Today's bills sent to clients",
            'cheques' => 'Due cheques marked for deposit',
            'followups' => 'Overdues nudged, no reply pending',
            'backup' => 'Backup verified in Settings → Updates',
        ];
    }

    public function variance(): float
    {
        return round((float) $this->cash_counted - (float) $this->expected_petty, 2);
    }
}
