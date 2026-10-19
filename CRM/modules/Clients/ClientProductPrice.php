<?php

namespace Modules\Clients;

use Illuminate\Database\Eloquent\Model;

class ClientProductPrice extends Model
{
    protected $fillable = ['client_id', 'product_id', 'rate'];

    protected $casts = ['rate' => 'decimal:2'];

    public function product()
    {
        return $this->belongsTo(\Modules\Products\Product::class);
    }
}
