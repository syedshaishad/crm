<?php

namespace Modules\Purchases;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['purchase_order_id', 'description', 'hsn_code', 'qty', 'unit', 'rate', 'tax_rate', 'amount'];
}
