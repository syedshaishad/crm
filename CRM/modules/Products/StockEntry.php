<?php

namespace Modules\Products;

use Illuminate\Database\Eloquent\Model;

class StockEntry extends Model
{
    protected $fillable = ['product_id', 'type', 'qty', 'reference', 'notes', 'moved_on', 'created_by'];

    protected $casts = ['qty' => 'decimal:2', 'moved_on' => 'date'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
