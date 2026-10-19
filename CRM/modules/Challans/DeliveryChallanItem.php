<?php

namespace Modules\Challans;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryChallanItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['delivery_challan_id', 'description', 'hsn_code', 'qty', 'unit', 'rate', 'amount'];
}
