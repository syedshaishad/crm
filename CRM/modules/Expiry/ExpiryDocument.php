<?php

namespace Modules\Expiry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpiryDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'title', 'category', 'ref_no', 'authority', 'valid_from', 'valid_to', 'remind_days', 'notes', 'created_by'];

    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date', 'remind_days' => 'integer'];

    public static function categories(): array
    {
        return ['gst_filing' => 'GST filing', 'license' => 'License', 'insurance' => 'Insurance', 'other' => 'Other'];
    }

    /** Live state from dates: expired / expiring (≤ remind_days) / valid / undated. */
    public function state(): string
    {
        if (! $this->valid_to) {
            return 'undated';
        }
        $today = now()->toDateString();
        if ($this->valid_to->toDateString() < $today) {
            return 'expired';
        }
        if ($this->valid_to->toDateString() <= now()->addDays((int) ($this->remind_days ?: 30))->toDateString()) {
            return 'expiring';
        }

        return 'valid';
    }
}
